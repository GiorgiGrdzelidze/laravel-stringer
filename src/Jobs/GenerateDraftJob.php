<?php

declare(strict_types=1);

namespace Stringer\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\DraftGenerator;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\TelegramClient;
use Throwable;

/**
 * Queued wrapper around `DraftGenerator::generate`.
 *
 * - `tries = 2`, `backoff = [30]` (one retry 30 seconds after the first
 *   failure; the second failure triggers `failed()` and the topic is
 *   marked Failed).
 * - Idempotency guard: if the topic is already in `Drafting` and its
 *   `updated_at` is within the last 5 minutes, the handler exits early.
 *   Covers the parallel-worker / requeue-while-running race.
 * - On final failure: `TopicQueue::markFailed` records the message,
 *   `Log::channel('daily')` writes a structured entry. Telegram
 *   notification of the operator who requested the topic ships in
 *   Phase 8 alongside the rest of the Telegram surface.
 */
final class GenerateDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const PARALLEL_RETRY_WINDOW_MINUTES = 5;

    public int $tries = 2;

    public function __construct(public readonly int $topicId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30];
    }

    public function handle(DraftGenerator $generator, TopicQueue $queue): void
    {
        $topic = BlogTopic::query()->find($this->topicId);

        if (! $topic instanceof BlogTopic) {
            // Topic was deleted between dispatch and handle. Nothing to do.
            return;
        }

        // Only honor the parallel-retry guard on the FIRST attempt of this
        // job. On retries the topic is legitimately in `drafting` from
        // our own previous attempt's markDrafting() — and the guard would
        // silently skip the retry, blocking failed() from ever running
        // and the operator from ever hearing about the failure.
        if ($this->attempts() <= 1 && $this->isRecentParallelRetry($topic)) {
            return;
        }

        $queue->markDrafting($topic);
        $generator->generate($topic);

        // DraftGenerator marks the topic Drafted inside its transaction.
        // From here we either hand off to the cover-image job (which
        // sends a richer photo-card notification once the cover lands)
        // or — when images are disabled — fire the text-only notification
        // directly. Either way the operator receives exactly one message
        // per drafted topic.
        if ((bool) config('stringer.images.enabled', true)) {
            GenerateCoverImageJob::dispatch($this->topicId);
        } else {
            $this->notifyDrafted($topic->fresh());
        }
    }

    public function failed(Throwable $exception): void
    {
        $topic = BlogTopic::query()->find($this->topicId);

        if ($topic instanceof BlogTopic) {
            app(TopicQueue::class)->markFailed($topic, $exception->getMessage());
        }

        Log::channel('daily')->error('stringer.generate_draft_job.failed', [
            'topic_id' => $this->topicId,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);

        if ($topic instanceof BlogTopic) {
            $this->notifyFailed($topic, $exception);
        }
    }

    private function notifyDrafted(?BlogTopic $topic): void
    {
        if (! $topic instanceof BlogTopic || $topic->requested_by_chat_id === null) {
            return;
        }

        try {
            $url = $topic->article_id !== null
                ? $this->resolveEditUrl($topic)
                : null;

            if ($url !== null) {
                $text = $this->formatDraftReadyMessage($topic, $url);
                app(TelegramClient::class)->sendMessage($topic->requested_by_chat_id, $text, 'HTML');
            } else {
                app(TelegramClient::class)->sendMessage(
                    $topic->requested_by_chat_id,
                    "Draft #{$topic->id} is ready.",
                );
            }
        } catch (Throwable $e) {
            Log::channel('daily')->warning('stringer.generate_draft_job.notify_drafted_failed', [
                'topic_id' => $topic->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Public entry point so chained jobs (`GenerateCoverImageJob` on
     * failure path) can reuse the same notification format without
     * cross-instantiating private methods through reflection.
     */
    public function notifyDraftedPublic(BlogTopic $topic): void
    {
        $this->notifyDrafted($topic);
    }

    /**
     * Static helper: build the admin edit URL for any model, applying
     * the admin-base-url rewrite. Shared by the cover-image job so the
     * photo-caption "Open in admin" link uses the same logic as the
     * text-only notification.
     */
    public static function resolveEditUrlFor(Model $article): string
    {
        $rawUrl = app(ContentTarget::class)->editUrl($article);

        $override = (string) config('stringer.telegram.admin_base_url', '');
        if ($override === '') {
            return $rawUrl;
        }

        $parts = parse_url($rawUrl);
        if ($parts === false || ! isset($parts['path'])) {
            return $rawUrl;
        }

        $tail = $parts['path'];
        if (isset($parts['query']) && $parts['query'] !== '') {
            $tail .= '?'.$parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $tail .= '#'.$parts['fragment'];
        }

        return rtrim($override, '/').'/'.ltrim($tail, '/');
    }

    private function notifyFailed(BlogTopic $topic, Throwable $exception): void
    {
        if ($topic->requested_by_chat_id === null) {
            return;
        }

        try {
            $reason = self::humanizeApiError($exception);

            $appName = (string) config('app.name', '');
            $host = $appName !== '' && $appName !== 'Laravel' ? $appName : '';
            $hostEsc = htmlspecialchars($host, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            $reasonEsc = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            $exceptionEsc = htmlspecialchars($exception::class, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            $head = $host !== ''
                ? "🌐 <b>{$hostEsc}</b> · ❌ Draft #{$topic->id} failed"
                : "❌ Draft #{$topic->id} failed";

            $text = implode("\n", [
                $head,
                "<i>{$reasonEsc}</i>",
                "<code>{$exceptionEsc}</code>",
            ]);

            app(TelegramClient::class)->sendMessage(
                $topic->requested_by_chat_id,
                $text,
                'HTML',
            );
        } catch (Throwable $e) {
            Log::channel('daily')->warning('stringer.generate_draft_job.notify_failed_notice_failed', [
                'topic_id' => $topic->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Shared error humanizer — pulls upstream JSON `message` fields out of
     * Laravel HTTP exceptions, strips boilerplate prefixes, and clamps to
     * a Telegram-friendly length. Used by both the draft-failure and
     * cover-failure notification paths so the operator sees the same
     * shape regardless of which step blew up.
     *
     * Tolerant of Guzzle's body-summary truncation: `RequestException`
     * clips the response body at ~120 chars and appends `(truncated...)`,
     * which means the closing `"` of the `"message"` field is often
     * missing. We accept either a real closing quote OR the truncation
     * marker so the operator still gets useful prose from a clipped body.
     */
    public static function humanizeApiError(Throwable $error): string
    {
        $message = $error->getMessage();

        // Match `"message": "..."`. Stops at the first real closing quote,
        // a backslash-escaped boundary, or Guzzle's `(truncated` marker —
        // whichever comes first.
        if (preg_match('/"message"\s*:\s*"(.{4,500}?)(?:"|\s*\(truncated)/su', $message, $matches) === 1) {
            $code = '';
            if (preg_match('/"code"\s*:\s*(\d{3})/', $message, $codeMatch) === 1) {
                $code = $codeMatch[1].' — ';
            }

            return mb_strimwidth($code.trim($matches[1]), 0, 280, '…');
        }

        $cleaned = preg_replace('/^HTTP request returned status code (\d{3}):\s*/i', '$1 — ', $message) ?? $message;
        $cleaned = preg_replace('/\s*\{.*$/s', '', $cleaned) ?? $cleaned;

        return mb_strimwidth(trim($cleaned), 0, 280, '…');
    }

    /**
     * Compose a richer "draft ready" message with title, word count, and a
     * tappable admin link. Uses Telegram HTML parse mode — caller is
     * responsible for passing parseMode='HTML'.
     */
    private function formatDraftReadyMessage(BlogTopic $topic, string $url): string
    {
        $primaryLocale = (string) config('stringer.locales.primary', 'en');
        $article = $topic->article;

        $title = '';
        $wordCount = 0;
        $tagCount = 0;
        $localeCount = 0;

        if ($article !== null) {
            $rawTitle = method_exists($article, 'getTranslation')
                ? (string) $article->getTranslation('title', $primaryLocale)
                : (string) ($article->title ?? '');
            $title = mb_strimwidth($rawTitle, 0, 80, '…');

            $body = method_exists($article, 'getTranslation')
                ? (string) $article->getTranslation('body', $primaryLocale)
                : (string) ($article->body ?? '');
            $wordCount = str_word_count(strip_tags($body));

            if (method_exists($article, 'tags')) {
                $tagCount = $article->tags()->count();
            }

            if (method_exists($article, 'getTranslations')) {
                $localeCount = count(array_filter(
                    (array) $article->getTranslations('title'),
                    static fn ($v): bool => is_string($v) && $v !== '',
                ));
            }
        }

        $titleLine = $title !== ''
            ? '<b>'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8').'</b>'
            : '<b>Untitled draft</b>';

        $stats = [];
        if ($wordCount > 0) {
            $stats[] = number_format($wordCount).' words';
        }
        if ($localeCount > 0) {
            $stats[] = $localeCount.' '.($localeCount === 1 ? 'locale' : 'locales');
        }
        if ($tagCount > 0) {
            $stats[] = $tagCount.' '.($tagCount === 1 ? 'tag' : 'tags');
        }
        $statsLine = $stats !== [] ? '<i>'.implode(' · ', $stats).'</i>' : '';

        $linkLine = sprintf(
            '🔗 <a href="%s">Open in admin</a>',
            htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
        );

        $host = $this->resolveHostBadge($url);
        $headLine = $host !== ''
            ? sprintf('🌐 <b>%s</b> · ✅ Draft #%d is ready', htmlspecialchars($host, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), $topic->id)
            : "✅ Draft #{$topic->id} is ready";

        $lines = [$headLine, $titleLine];
        if ($statsLine !== '') {
            $lines[] = $statsLine;
        }
        $lines[] = $linkLine;

        return implode("\n", $lines);
    }

    /**
     * Pull a short host name to badge the notification with. Prefer
     * APP_NAME when set (most readable: "Grdzelo"), otherwise extract
     * the bare hostname from the admin URL. Returns an empty string
     * when nothing useful is available, in which case the caller
     * falls back to the un-badged headline.
     */
    private function resolveHostBadge(string $url): string
    {
        $appName = (string) config('app.name', '');
        if ($appName !== '' && $appName !== 'Laravel') {
            return $appName;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    private function resolveEditUrl(BlogTopic $topic): ?string
    {
        if ($topic->article_id === null) {
            return null;
        }

        $article = $topic->article;

        if ($article === null) {
            return null;
        }

        return $this->rewriteAdminHost(app(ContentTarget::class)->editUrl($article));
    }

    /**
     * Swap the host portion of an admin URL with `stringer.telegram.admin_base_url`
     * when set. Lets local-dev installs send a publicly reachable URL (e.g. an
     * ngrok forward) over Telegram instead of an unclickable `http://localhost/...`.
     * Production typically leaves this unset → the URL passes through unchanged.
     */
    private function rewriteAdminHost(string $url): string
    {
        $override = (string) config('stringer.telegram.admin_base_url', '');

        if ($override === '') {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['path'])) {
            return $url;
        }

        $tail = $parts['path'];
        if (isset($parts['query']) && $parts['query'] !== '') {
            $tail .= '?'.$parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $tail .= '#'.$parts['fragment'];
        }

        return rtrim($override, '/').'/'.ltrim($tail, '/');
    }

    private function isRecentParallelRetry(BlogTopic $topic): bool
    {
        if ($topic->status !== TopicStatus::Drafting) {
            return false;
        }

        $cutoff = Date::now()->subMinutes(self::PARALLEL_RETRY_WINDOW_MINUTES);

        return $topic->updated_at->greaterThan($cutoff);
    }
}

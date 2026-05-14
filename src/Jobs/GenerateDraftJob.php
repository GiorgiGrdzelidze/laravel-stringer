<?php

declare(strict_types=1);

namespace Stringer\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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

        if ($this->isRecentParallelRetry($topic)) {
            return;
        }

        $queue->markDrafting($topic);
        $generator->generate($topic);
        // DraftGenerator marks the topic Drafted inside its transaction
        // when the host adapter successfully writes; here we just fire the
        // requester-notification side effect.
        $this->notifyDrafted($topic->fresh());
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
                'topic_id' => $this->topicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyFailed(BlogTopic $topic, Throwable $exception): void
    {
        if ($topic->requested_by_chat_id === null) {
            return;
        }

        try {
            $reason = mb_strimwidth($exception->getMessage(), 0, 200, '…');
            app(TelegramClient::class)->sendMessage(
                $topic->requested_by_chat_id,
                "Draft #{$topic->id} generation failed: {$reason}",
            );
        } catch (Throwable $e) {
            Log::channel('daily')->warning('stringer.generate_draft_job.notify_failed_notice_failed', [
                'topic_id' => $this->topicId,
                'error' => $e->getMessage(),
            ]);
        }
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
                ? (string) $article->getTranslation('title', $primaryLocale, '')
                : (string) ($article->title ?? '');
            $title = mb_strimwidth($rawTitle, 0, 80, '…');

            $body = method_exists($article, 'getTranslation')
                ? (string) $article->getTranslation('body', $primaryLocale, '')
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

        $lines = ["✅ Draft #{$topic->id} is ready", $titleLine];
        if ($statsLine !== '') {
            $lines[] = $statsLine;
        }
        $lines[] = $linkLine;

        return implode("\n", $lines);
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

        return app(ContentTarget::class)->editUrl($article);
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

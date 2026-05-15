<?php

declare(strict_types=1);

namespace Stringer\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\ImageManager;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Telegram\TelegramClient;
use Throwable;

/**
 * Dispatched immediately after `GenerateDraftJob` commits a draft.
 *
 * Produces a cover image via the configured `ImageManager` driver,
 * hands the bytes to the host's `ContentTarget::attachCover`, and exits.
 * Failure is non-fatal for the draft — a missing cover should never
 * unwind the article. We log and move on.
 *
 * `driverOverride` lets the operator-confirmation flow re-run the job
 * against a different driver ("🖼 Try Unsplash") without mutating
 * global config. `regenerateCount` enforces the per-topic ceiling from
 * `config('stringer.images.max_regenerates_per_topic')`.
 */
final class GenerateCoverImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $topicId,
        public readonly ?string $driverOverride = null,
        public readonly int $regenerateCount = 0,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30];
    }

    public function handle(
        ImageManager $images,
        PromptBuilder $prompts,
        ContentTarget $target,
        ConfigRepository $config,
        LlmClient $llm,
    ): void {
        if (! (bool) $config->get('stringer.images.enabled', true)) {
            return;
        }

        $topic = BlogTopic::query()->find($this->topicId);

        if (! $topic instanceof BlogTopic || $topic->article === null) {
            return;
        }

        $maxRegenerates = (int) $config->get('stringer.images.max_regenerates_per_topic', 3);
        if ($this->regenerateCount > $maxRegenerates) {
            Log::channel('daily')->info('stringer.generate_cover_image_job.regenerate_ceiling_hit', [
                'topic_id' => $this->topicId,
                'attempt' => $this->regenerateCount,
                'max' => $maxRegenerates,
            ]);

            return;
        }

        $article = $topic->article;
        $primaryLocale = (string) $config->get('stringer.locales.primary', 'en');

        $title = method_exists($article, 'getTranslation')
            ? (string) $article->getTranslation('title', $primaryLocale)
            : (string) ($article->title ?? '');
        $excerpt = method_exists($article, 'getTranslation')
            ? (string) $article->getTranslation('excerpt', $primaryLocale)
            : (string) ($article->excerpt ?? '');
        $style = (string) $config->get('stringer.images.style', '');

        try {
            // Step 1: render the meta-instruction template (DB-editable or
            // baked-in fallback). The output is an LLM-targeted prompt
            // telling the model how to write a visual prompt — NOT yet a
            // visual prompt itself.
            $instructionPrompt = $prompts->buildImagePrompt($title, $excerpt, $style);

            // Step 2: ask the LLM to produce the actual visual prompt. One
            // extra round-trip per cover; in exchange every cover gets a
            // topic-tailored scene description instead of a templated
            // boilerplate that the image model would interpret literally.
            $rawVisualPrompt = $llm->draft($instructionPrompt, []);
            $visualPrompt = $this->normalizeVisualPrompt($rawVisualPrompt);

            $driver = $this->driverOverride !== null && $this->driverOverride !== ''
                ? $images->with($this->driverOverride)
                : $images->make();

            $image = $driver->generate(
                $visualPrompt,
                new ImageGenerationOptions(
                    size: (string) $config->get('stringer.images.master_size', '1792x1024'),
                    style: $style,
                    seed: $this->regenerateCount > 0 ? random_int(1, 1_000_000) : null,
                ),
            );

            $target->attachCover($article, $image);

            // Cover attached successfully — send a rich photo-card
            // notification with the cover preview + title + excerpt + stats.
            $this->notifyDraftedWithPhoto($topic->fresh(), $title, $excerpt);
        } catch (Throwable $e) {
            Log::channel('daily')->warning('stringer.generate_cover_image_job.failed', [
                'topic_id' => $this->topicId,
                'driver' => $this->driverOverride ?? $images->defaultDriver(),
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            // Cover failed — notify the operator with the draft summary *and*
            // a short, human-readable cover-failure note. Surfaces upstream
            // issues (LLM rate limit, image-API quota, paid-plan-required)
            // without forcing the operator to read the logs.
            $this->notifyCoverFailedToOperator(
                $topic->fresh(),
                $this->driverOverride ?? $images->defaultDriver(),
                $e,
            );
        }
    }

    private function notifyDraftedWithPhoto(?BlogTopic $topic, string $title, string $excerpt): void
    {
        if (! $topic instanceof BlogTopic || $topic->requested_by_chat_id === null) {
            return;
        }

        $article = $topic->article;
        if ($article === null || ! method_exists($article, 'getFirstMedia')) {
            $this->notifyDraftedTextOnly($topic);

            return;
        }

        $cover = $article->getFirstMedia('cover');
        $photoPath = is_object($cover) && method_exists($cover, 'getPath') ? $cover->getPath() : null;

        if (! is_string($photoPath) || ! is_file($photoPath)) {
            $this->notifyDraftedTextOnly($topic);

            return;
        }

        try {
            $caption = $this->buildCaption($topic, $title, $excerpt);

            app(TelegramClient::class)->sendPhoto(
                $topic->requested_by_chat_id,
                $photoPath,
                $caption,
                'HTML',
            );
        } catch (Throwable $e) {
            Log::channel('daily')->warning('stringer.cover_image_notification_failed', [
                'topic_id' => $this->topicId,
                'error' => $e->getMessage(),
            ]);

            // Telegram rejected the photo (oversize, bad mime, etc.) — fall
            // back to text so the operator still gets the notification.
            $this->notifyDraftedTextOnly($topic);
        }
    }

    private function notifyDraftedTextOnly(?BlogTopic $topic): void
    {
        if (! $topic instanceof BlogTopic) {
            return;
        }

        (new GenerateDraftJob($topic->id))->notifyDraftedPublic($topic);
    }

    /**
     * Send a single Telegram message that summarises the drafted article
     * AND surfaces why the cover image failed. Saves the operator a trip
     * to the logs when something upstream (quota, paid-plan-required,
     * model-not-found) blocked image generation.
     */
    private function notifyCoverFailedToOperator(?BlogTopic $topic, string $driver, Throwable $error): void
    {
        if (! $topic instanceof BlogTopic || $topic->requested_by_chat_id === null) {
            return;
        }

        try {
            $reason = GenerateDraftJob::humanizeApiError($error);

            $editUrl = $topic->article !== null
                ? GenerateDraftJob::resolveEditUrlFor($topic->article)
                : '';

            $appName = (string) config('app.name', '');
            $host = $appName !== '' && $appName !== 'Laravel'
                ? $appName
                : (string) (parse_url($editUrl, PHP_URL_HOST) ?: '');
            $host = preg_replace('/^www\./', '', $host) ?? $host;

            $hostEsc = htmlspecialchars($host, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            $driverEsc = htmlspecialchars($driver, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            $reasonEsc = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            $head = $host !== ''
                ? "🌐 <b>{$hostEsc}</b> · ✅ Draft #{$topic->id} is ready"
                : "✅ Draft #{$topic->id} is ready";

            $lines = [
                $head,
                "⚠️ <b>Cover generation failed</b> via <code>{$driverEsc}</code>",
                "<i>{$reasonEsc}</i>",
            ];

            if ($editUrl !== '') {
                $lines[] = sprintf(
                    '🔗 <a href="%s">Open in admin</a>',
                    htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
                );
            }

            app(TelegramClient::class)->sendMessage(
                $topic->requested_by_chat_id,
                implode("\n", $lines),
                'HTML',
            );
        } catch (Throwable $e) {
            Log::channel('daily')->warning('stringer.cover_failure_notification_failed', [
                'topic_id' => $this->topicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildCaption(BlogTopic $topic, string $title, string $excerpt): string
    {
        $article = $topic->article;
        $primaryLocale = (string) config('stringer.locales.primary', 'en');

        $wordCount = 0;
        $localeCount = 0;
        $tagCount = 0;

        if ($article !== null) {
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

        $editUrl = $article !== null
            ? GenerateDraftJob::resolveEditUrlFor($article)
            : null;

        $titleEsc = htmlspecialchars(mb_strimwidth($title, 0, 120, '…'), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $excerptEsc = htmlspecialchars(mb_strimwidth($excerpt, 0, 280, '…'), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        $appName = (string) config('app.name', '');
        $host = $appName !== '' && $appName !== 'Laravel'
            ? $appName
            : (string) (parse_url((string) $editUrl, PHP_URL_HOST) ?: '');
        $host = preg_replace('/^www\./', '', $host) ?? $host;

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

        $headLine = $host !== ''
            ? sprintf('🌐 <b>%s</b> · ✅ Draft #%d is ready', htmlspecialchars($host, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), $topic->id)
            : "✅ Draft #{$topic->id} is ready";

        $lines = [$headLine, "<b>{$titleEsc}</b>"];
        if ($excerptEsc !== '') {
            $lines[] = $excerptEsc;
        }
        if ($statsLine !== '') {
            $lines[] = $statsLine;
        }
        if ($editUrl !== null && $editUrl !== '') {
            $lines[] = sprintf(
                '🔗 <a href="%s">Open in admin</a>',
                htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * LLMs occasionally pad the visual prompt with preamble ("Sure! Here is
     * the visual prompt:"), wrap it in code fences, or sandwich it in
     * quotes. The image model copes badly with any of that. Strip those
     * common shapes and clamp the result to a sensible length so a
     * runaway response can't blow up the image-API payload.
     */
    private function normalizeVisualPrompt(string $raw): string
    {
        $cleaned = trim($raw);

        // Drop code-fence wrappers ```...``` or ```text\n...\n```.
        $cleaned = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/u', '', $cleaned) ?? $cleaned;

        // Drop leading preambles like "Sure, here is …:" / "Visual prompt:".
        $cleaned = preg_replace(
            '/^(?:sure[,!]?\s+|here(?:\s+is|\s+\'s)\s+(?:the\s+)?(?:visual\s+)?prompt[:.\s]*|visual\s+prompt[:.\s]*)/iu',
            '',
            $cleaned
        ) ?? $cleaned;

        // Strip outer quotes if the whole string is wrapped.
        if (
            mb_strlen($cleaned) >= 2
            && in_array(mb_substr($cleaned, 0, 1), ['"', "'", '«', '“', '„'], true)
            && in_array(mb_substr($cleaned, -1), ['"', "'", '»', '”'], true)
        ) {
            $cleaned = mb_substr($cleaned, 1, mb_strlen($cleaned) - 2);
        }

        // Collapse internal whitespace runs so the prompt stays a single line.
        $cleaned = preg_replace('/\s+/u', ' ', trim($cleaned)) ?? $cleaned;

        // 1500 chars is way over any real visual prompt — hard guard rail.
        if (mb_strlen($cleaned) > 1500) {
            $cleaned = mb_substr($cleaned, 0, 1500);
        }

        return $cleaned;
    }
}

<?php

declare(strict_types=1);

namespace Stringer\Laravel\Prompts;

use Illuminate\Database\QueryException;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerPrompt;

/**
 * Default `PromptBuilder` binding. Reads `StringerPrompt` rows so the
 * operator can edit prompts via Filament; falls back to
 * `DefaultPromptBuilder`'s baked-in templates when no matching row is
 * active in the DB — covers fresh-install state and the testing env.
 *
 * Translation lookup is locale-specific first, then locale-agnostic:
 * `(key=translate, locale=ka)` beats `(key=translate, locale=null)`.
 * Drafting uses the locale-agnostic row only — `(key=draft, locale=null)`.
 */
final class DbPromptBuilder implements PromptBuilder
{
    public function __construct(
        private readonly DefaultPromptBuilder $fallback,
    ) {}

    public function buildDraftPrompt(BlogTopic $topic, array $context, array $fields): string
    {
        $template = $this->lookup('draft', null);

        if ($template === null) {
            return $this->fallback->buildDraftPrompt($topic, $context, $fields);
        }

        return $this->fallback->renderDraft($template, $topic, $context, $fields);
    }

    public function buildTranslationPrompt(string $englishText, string $targetLocale): string
    {
        $template = $this->lookup('translate', $targetLocale)
            ?? $this->lookup('translate', null);

        if ($template === null) {
            return $this->fallback->buildTranslationPrompt($englishText, $targetLocale);
        }

        return $this->fallback->renderTranslation($template, $englishText, $targetLocale);
    }

    public function buildImagePrompt(string $title, string $excerpt, string $style): string
    {
        $template = $this->lookup('cover_image', null);

        if ($template === null) {
            return $this->fallback->buildImagePrompt($title, $excerpt, $style);
        }

        return $this->fallback->renderImage($template, $title, $excerpt, $style);
    }

    private function lookup(string $key, ?string $locale): ?string
    {
        try {
            $prompt = StringerPrompt::query()
                ->where('key', $key)
                ->where('locale', $locale)
                ->where('is_active', true)
                ->first();
        } catch (QueryException) {
            // The stringer_prompts table may not exist yet (pre-migrate
            // boot) or the DB may be unreachable. Anything else — like
            // a malformed model or coding error — bubbles up.
            return null;
        }

        return $prompt?->content;
    }
}

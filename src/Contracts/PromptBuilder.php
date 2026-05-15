<?php

declare(strict_types=1);

namespace Stringer\Laravel\Contracts;

use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerContentField;

/**
 * Produces the actual strings sent to the LLM.
 *
 * Bound by default to `DbPromptBuilder`, which reads `StringerPrompt`
 * rows and falls back to `DefaultPromptBuilder` when the DB has no
 * matching template. Hosts can override the binding with a programmatic
 * implementation if they want to skip the DB layer entirely.
 */
interface PromptBuilder
{
    /**
     * Build the draft-generation prompt for one topic.
     *
     * @param  array<string, mixed>  $context  Output of `ContextBuilder::build()`.
     * @param  list<StringerContentField>  $fields  Active field schema rows.
     */
    public function buildDraftPrompt(BlogTopic $topic, array $context, array $fields): string;

    /**
     * Build the translation prompt for a draft already produced in the
     * primary locale.
     */
    public function buildTranslationPrompt(string $englishText, string $targetLocale): string;

    /**
     * Build the visual-prompt string for the cover-image driver. Inputs
     * are deliberately scalar so the prompt builder doesn't need to know
     * about host models — callers project `title` + `excerpt` from the
     * already-saved article and pick the global style suffix.
     */
    public function buildImagePrompt(string $title, string $excerpt, string $style): string;
}

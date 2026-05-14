<?php

declare(strict_types=1);

namespace Stringer\Laravel\Prompts;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerContentField;

/**
 * Baked-in fallback `PromptBuilder` implementation.
 *
 * Owns the canonical template strings + `{{placeholder}}` rendering.
 * `DbPromptBuilder` delegates back to the public `renderDraft()` /
 * `renderTranslation()` methods here when it finds a DB-managed template,
 * so the two builders share one substitution implementation.
 */
final class DefaultPromptBuilder implements PromptBuilder
{
    public const DRAFT_TEMPLATE = <<<'TEMPLATE'
You are drafting a blog post for human review.

Voice: {{voice}}

Source: {{source}}
Topic hint: {{hint}}

Reference context — recently published content on the site:
{{context}}

Available categories — choose ONE slug or null:
{{categories}}

Field schema — produce a single JSON object keyed by field name. Each
field's value type is described below; honor the constraints exactly.
{{field_schema}}

Output the JSON only — no code fences, no preamble, no trailing prose.
TEMPLATE;

    public const TRANSLATE_TEMPLATE = <<<'TEMPLATE'
Translate the following text from English to {{target_locale}}. Preserve
markdown structure, code blocks, JSON keys, HTML tags, and proper nouns.
Output only the translation; no preamble, no quoting.

{{english_text}}
TEMPLATE;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function buildDraftPrompt(BlogTopic $topic, array $context, array $fields): string
    {
        return $this->renderDraft(self::DRAFT_TEMPLATE, $topic, $context, $fields);
    }

    public function buildTranslationPrompt(string $englishText, string $targetLocale): string
    {
        return $this->renderTranslation(self::TRANSLATE_TEMPLATE, $englishText, $targetLocale);
    }

    /**
     * Render an arbitrary draft-shaped template with the same placeholder
     * protocol as the baked-in template — used by `DbPromptBuilder` to
     * apply substitutions to a DB-loaded template.
     *
     * @param  array<string, mixed>  $context
     * @param  list<StringerContentField>  $fields
     */
    public function renderDraft(string $template, BlogTopic $topic, array $context, array $fields): string
    {
        return self::render($template, [
            'voice' => (string) $this->config->get('stringer.voice.default', 'clear, accurate, no marketing fluff'),
            'source' => $topic->source->value,
            'hint' => $topic->hint,
            'context' => self::formatContext($context),
            'categories' => self::formatCategories($this->categoriesFromContext($context)),
            'field_schema' => self::formatFieldSchema($fields),
        ]);
    }

    public function renderTranslation(string $template, string $englishText, string $targetLocale): string
    {
        return self::render($template, [
            'english_text' => $englishText,
            'target_locale' => $targetLocale,
        ]);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private static function render(string $template, array $vars): string
    {
        $pairs = [];
        foreach ($vars as $key => $value) {
            $pairs['{{'.$key.'}}'] = $value;
        }

        return strtr($template, $pairs);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function formatContext(array $context): string
    {
        $sections = [];

        foreach (['articles', 'projects', 'repositories'] as $key) {
            if (! isset($context[$key]) || ! is_array($context[$key]) || $context[$key] === []) {
                continue;
            }

            $lines = ["## {$key}"];
            foreach ($context[$key] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $title = (string) ($entry['title'] ?? '');
                $excerpt = (string) ($entry['excerpt'] ?? '');
                $lines[] = "- {$title} — {$excerpt}";
            }

            $sections[] = implode("\n", $lines);
        }

        return $sections === [] ? '(no reference content available)' : implode("\n\n", $sections);
    }

    /**
     * @param  list<array{name: string, slug: string, description?: string}>  $categories
     */
    private static function formatCategories(array $categories): string
    {
        if ($categories === []) {
            return '(no categories defined; output null for category)';
        }

        $lines = [];
        foreach ($categories as $category) {
            $name = (string) ($category['name'] ?? '');
            $slug = (string) ($category['slug'] ?? '');
            $description = trim((string) ($category['description'] ?? ''));
            $suffix = $description === '' ? '' : " — {$description}";
            $lines[] = "- {$slug}: {$name}{$suffix}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<StringerContentField>  $fields
     */
    private static function formatFieldSchema(array $fields): string
    {
        if ($fields === []) {
            return '(no fields configured)';
        }

        $lines = [];
        foreach ($fields as $field) {
            $constraints = self::summarizeConstraints($field);
            $constraintsSuffix = $constraints === '' ? '' : " ({$constraints})";
            $required = $field->is_required ? 'required' : 'optional';

            $lines[] = sprintf(
                '- %s [%s, %s]%s: %s',
                $field->name,
                $field->type->value,
                $required,
                $constraintsSuffix,
                $field->instruction,
            );
        }

        return implode("\n", $lines);
    }

    private static function summarizeConstraints(StringerContentField $field): string
    {
        $parts = [];

        if ($field->type->isTranslatable() && $field->locales !== null && $field->locales !== []) {
            $parts[] = 'locales='.implode('/', $field->locales);
        }
        if ($field->max_length !== null) {
            $parts[] = "max_length={$field->max_length}";
        }
        if ($field->max_words !== null) {
            $parts[] = "max_words={$field->max_words}";
        }
        if ($field->min !== null) {
            $parts[] = "min={$field->min}";
        }
        if ($field->max !== null) {
            $parts[] = "max={$field->max}";
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{name: string, slug: string, description?: string}>
     */
    private function categoriesFromContext(array $context): array
    {
        if (! isset($context['categories']) || ! is_array($context['categories'])) {
            return [];
        }

        /** @var list<array{name: string, slug: string, description?: string}> */
        return array_values($context['categories']);
    }
}

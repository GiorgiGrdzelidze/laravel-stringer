<?php

declare(strict_types=1);

namespace Stringer\Laravel\Enums;

/**
 * Backed enum naming the shape of a single `StringerContentField` value.
 *
 * `ArticleTarget::write()` dispatches on this when copying `LocalizedDraft`
 * fields onto the host's Eloquent model:
 *
 * - `TranslatableString` / `TranslatableMarkdown` → `setTranslations($name, $perLocaleValues)`
 * - `String` / `Markdown` → direct property assignment
 * - `TagList` → `syncTags($value)` (Spatie tags)
 * - `Category` → slug → host's category model FK
 * - `Integer` → `(int) $value` then direct assignment
 */
enum FieldType: string
{
    case TranslatableString = 'translatable_string';
    case TranslatableMarkdown = 'translatable_markdown';
    case String = 'string';
    case Markdown = 'markdown';
    case TagList = 'tag_list';
    case Category = 'category';
    case Integer = 'integer';

    public function isTranslatable(): bool
    {
        return $this === self::TranslatableString || $this === self::TranslatableMarkdown;
    }
}

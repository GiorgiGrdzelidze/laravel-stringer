<?php

declare(strict_types=1);

namespace Stringer\Laravel\DataObjects;

use InvalidArgumentException;

/**
 * Immutable, dynamic-shape draft passed from `DraftGenerator` to the
 * host's `ContentTarget`.
 *
 * Keys are `StringerContentField.name` values; their value shape depends
 * on the field's `FieldType`:
 *
 * - `TranslatableString` / `TranslatableMarkdown` → `array<string, string>`
 *   keyed by locale, e.g. `['en' => '…', 'ka' => '…', 'ru' => '…']`.
 * - `String` / `Markdown` → `string`.
 * - `TagList` → `list<string>`.
 * - `Category` → `?string` (the slug) or null.
 * - `Integer` → `int`.
 *
 * The DTO does not enforce per-field shape — that's the host adapter's job
 * via the field-type `match()` in `ArticleTarget::write()`.
 */
final readonly class LocalizedDraft
{
    /**
     * @param  array<string, mixed>  $fields  Keyed by `StringerContentField.name`.
     */
    public function __construct(public array $fields) {}

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }

    public function field(string $name): mixed
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Field '{$name}' is not in the draft.");
        }

        return $this->fields[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->fields);
    }
}

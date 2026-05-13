<?php

declare(strict_types=1);

namespace Stringer\Laravel\DataObjects;

use InvalidArgumentException;

/**
 * Immutable per-locale draft passed from the generator to the host's
 * ContentTarget. Each locale carries title + excerpt + body; the primary
 * locale is the one the LLM drafted in (others are translations).
 */
final readonly class LocalizedDraft
{
    /**
     * @param  array<string, array{title: string, excerpt: string, body: string}>  $translations
     */
    public function __construct(
        public array $translations,
        public string $primaryLocale,
    ) {
        if ($translations === []) {
            throw new InvalidArgumentException('LocalizedDraft requires at least one translation.');
        }

        if (! array_key_exists($primaryLocale, $translations)) {
            throw new InvalidArgumentException(
                "Primary locale '{$primaryLocale}' is not present in translations."
            );
        }
    }

    /**
     * @return array{title: string, excerpt: string, body: string}
     */
    public function forLocale(string $locale): array
    {
        if (! array_key_exists($locale, $this->translations)) {
            throw new InvalidArgumentException("Locale '{$locale}' is not in the draft.");
        }

        return $this->translations[$locale];
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_keys($this->translations);
    }
}

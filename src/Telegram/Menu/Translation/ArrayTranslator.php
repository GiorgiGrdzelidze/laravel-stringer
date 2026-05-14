<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Translation;

use Stringer\Laravel\Telegram\Menu\Contracts\MenuTranslator;

/**
 * Translator backed by PHP arrays — one dictionary per locale.
 *
 * Universal across hosts: the package ships an English dictionary for the
 * built-in screens (LanguagePicker, Back button, generic errors). Hosts
 * append their own keys via `extend()`.
 *
 * Fallback chain: requested locale → fallback locale → key itself (as a
 * loud sentinel so missing keys show up in chat instead of crashing).
 */
final class ArrayTranslator implements MenuTranslator
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $dictionaries;

    /**
     * @param  array<string, array<string, string>>  $dictionaries  ['en' => ['key' => 'value', ...], 'ka' => [...]]
     */
    public function __construct(
        array $dictionaries = [],
        private readonly string $fallbackLocale = 'en',
    ) {
        $this->dictionaries = $dictionaries;
    }

    public function translate(string $key, string $locale, array $params = []): string
    {
        $value = $this->dictionaries[$locale][$key]
            ?? $this->dictionaries[$this->fallbackLocale][$key]
            ?? '['.$key.']';

        if ($params === []) {
            return $value;
        }

        $pairs = [];
        foreach ($params as $name => $replacement) {
            $pairs[':'.$name] = (string) $replacement;
        }

        return strtr($value, $pairs);
    }

    /**
     * Merge additional keys into an existing locale dictionary, used by hosts
     * to register their own translation keys without replacing the built-ins.
     *
     * @param  array<string, string>  $entries
     */
    public function extend(string $locale, array $entries): void
    {
        $this->dictionaries[$locale] = array_merge(
            $this->dictionaries[$locale] ?? [],
            $entries,
        );
    }

    /**
     * @return list<string> Locale codes the translator knows about (any locale with at least one key)
     */
    public function knownLocales(): array
    {
        return array_keys($this->dictionaries);
    }
}

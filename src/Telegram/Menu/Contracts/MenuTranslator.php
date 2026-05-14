<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Contracts;

/**
 * Resolves a translation key into a localised string.
 *
 * Hosts can pick the `ArrayTranslator` (ships with the package) or wire any
 * other implementation — Laravel's translator, a database-backed source, etc.
 */
interface MenuTranslator
{
    /**
     * @param  array<string, string|int|float>  $params  `:placeholder` substitutions
     */
    public function translate(string $key, string $locale, array $params = []): string;
}

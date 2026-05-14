<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Contracts;

/**
 * Host-supplied source of category listings for the menu's category-pick flow.
 *
 * The package can't import the host's category model directly (G3 boundary).
 * Hosts implement this to expose their published / visible categories in a
 * shape the menu can render.
 *
 * Returned entries should already be filtered to what the LLM is allowed to
 * choose from — `is_visible`, `is_published`, etc. The menu does no further
 * filtering.
 */
interface CategoryDirectory
{
    /**
     * @return list<array{slug: string, name: string, description?: string|null}>
     */
    public function listForLocale(string $locale): array;

    /**
     * Resolve a slug back to its display name in the chat's locale. Returns
     * null when no category with that slug exists (the menu treats this as a
     * stale tap and falls back to the categories list).
     */
    public function findName(string $slug, string $locale): ?string;
}

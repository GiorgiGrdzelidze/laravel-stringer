<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu;

use Stringer\Laravel\Telegram\Menu\Contracts\CategoryDirectory;

/**
 * Default `CategoryDirectory` for hosts that haven't bound their own.
 *
 * Returns an empty list — the menu's CategoriesNode renders its "no
 * categories configured yet" body in that case. Hosts that want category
 * support override this binding in their own ServiceProvider:
 *
 *     $this->app->bind(
 *         \Stringer\Laravel\Telegram\Menu\Contracts\CategoryDirectory::class,
 *         \App\Stringer\ArticleCategoryDirectory::class,
 *     );
 */
final class NullCategoryDirectory implements CategoryDirectory
{
    public function listForLocale(string $locale): array
    {
        return [];
    }

    public function findName(string $slug, string $locale): ?string
    {
        return null;
    }
}

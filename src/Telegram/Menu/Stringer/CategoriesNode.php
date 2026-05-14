<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Telegram\Menu\Contracts\CategoryDirectory;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\KeyboardLayoutAware;
use Stringer\Laravel\Telegram\Menu\PendingInputStore;

/**
 * Category list — reads from the host's `CategoryDirectory` adapter.
 *
 * When the host hasn't bound a directory or returns no categories, the node
 * shows a friendly empty state instead of a blank keyboard.
 */
final class CategoriesNode implements KeyboardLayoutAware, MenuNode
{
    public function __construct(
        private readonly CategoryDirectory $categories,
        private readonly PendingInputStore $pendingInputs,
    ) {}

    public function key(): string
    {
        return 'categories';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.categories.title');
    }

    public function body(MenuContext $ctx): ?string
    {
        if ($this->categories->listForLocale($ctx->locale) === []) {
            return $ctx->t('stringer.categories.empty');
        }

        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.root.categories');
    }

    public function children(MenuContext $ctx): iterable
    {
        $categories = $this->categories->listForLocale($ctx->locale);

        $nodes = [];
        foreach ($categories as $category) {
            $nodes[] = new CategoryActionsNode(
                slug: $category['slug'],
                displayName: $category['name'],
                pendingInputs: $this->pendingInputs,
            );
        }

        return $nodes;
    }

    public function onSelected(MenuContext $ctx): ?MenuResult
    {
        return null;
    }

    public function keyboardLayout(MenuContext $ctx): array
    {
        $rows = [];
        $row = [];
        foreach ($this->categories->listForLocale($ctx->locale) as $category) {
            $row[] = $category['name'];
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        $rows[] = [$ctx->t('menu.back')];

        return $rows;
    }
}

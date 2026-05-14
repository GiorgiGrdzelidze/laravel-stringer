<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\KeyboardLayoutAware;
use Stringer\Laravel\Telegram\Menu\PendingInputStore;

/**
 * Generate-mode picker. Three sub-options:
 *  - By topic hint   → prompts for free text and enqueues a Manual topic.
 *  - By category     → drills into the categories list.
 *  - Auto pick       → fires the auto-generate job immediately.
 */
final class GenerateNode implements KeyboardLayoutAware, MenuNode
{
    public function __construct(
        private readonly CategoriesNode $categoriesNode,
        private readonly PendingInputStore $pendingInputs,
    ) {}

    public function key(): string
    {
        return 'generate';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.generate.title');
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.root.generate');
    }

    public function children(MenuContext $ctx): iterable
    {
        return [
            new GenerateByHintNode($this->pendingInputs),
            $this->categoriesNode,
            new GenerateAutoNode,
        ];
    }

    public function onSelected(MenuContext $ctx): ?MenuResult
    {
        return null;
    }

    public function keyboardLayout(MenuContext $ctx): array
    {
        return [
            [$ctx->t('stringer.generate.hint')],
            [$ctx->t('stringer.generate.category')],
            [$ctx->t('stringer.generate.auto')],
            [$ctx->t('menu.back')],
        ];
    }
}

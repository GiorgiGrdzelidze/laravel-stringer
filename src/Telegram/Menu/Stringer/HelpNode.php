<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\KeyboardLayoutAware;

/**
 * Static help screen — same content as `/help` but reachable via the menu
 * and accompanied by a back button so users don't have to type `/start` to
 * return.
 */
final class HelpNode implements KeyboardLayoutAware, MenuNode
{
    public function key(): string
    {
        return 'help';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.help.title');
    }

    public function body(MenuContext $ctx): string
    {
        return $ctx->t('stringer.help.body');
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.root.help');
    }

    public function children(MenuContext $ctx): iterable
    {
        return [];
    }

    public function onSelected(MenuContext $ctx): ?MenuResult
    {
        return null;
    }

    public function keyboardLayout(MenuContext $ctx): array
    {
        return [[$ctx->t('menu.back')]];
    }
}

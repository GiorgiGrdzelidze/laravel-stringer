<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu;

use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;

/**
 * Optional companion interface for `MenuNode` — implement when you want
 * full control over the reply-keyboard layout (variable row widths, custom
 * ordering) instead of the renderer's default "two buttons per row".
 *
 * Returning an empty array hides the keyboard entirely (useful for terminal
 * action confirmation screens that should remove the menu).
 */
interface KeyboardLayoutAware
{
    /**
     * Return the keyboard as a list of rows, each row a list of button labels.
     *
     * @return list<list<string>>
     */
    public function keyboardLayout(MenuContext $ctx): array;
}

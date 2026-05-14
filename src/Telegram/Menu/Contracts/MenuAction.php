<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Contracts;

use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;

/**
 * A side-effect-bearing action attached to a leaf menu node.
 *
 * Use this when the action logic is non-trivial enough to warrant its own
 * class — composable, testable in isolation, reusable across multiple nodes.
 * Simple leaves can stay inside `MenuNode::onSelected()` directly.
 */
interface MenuAction
{
    public function execute(MenuContext $ctx): MenuResult;
}

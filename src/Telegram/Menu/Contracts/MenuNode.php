<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Contracts;

use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;

/**
 * One screen of the Telegram menu.
 *
 * Each implementation represents a single screen the user can land on. The
 * router resolves an incoming message (typically a button tap from the reply
 * keyboard, which arrives as plain text) into one of:
 *
 *  - **navigation** — the message matches a child node's label, the router
 *    follows the link and renders that child.
 *  - **action** — the message matches a leaf node, the router calls
 *    `onSelected()` and renders the returned `MenuResult`.
 *  - **fall-through** — the message matches nothing in the current screen, the
 *    router hands off to the host's regular text-command dispatcher.
 *
 * Implementations must be deterministic with respect to `MenuContext` —
 * the same context must produce the same title, body, and children.
 * `onSelected()` is the only place side effects belong.
 */
interface MenuNode
{
    /**
     * Stable identifier for this node, used in the current_path column and to
     * resolve "← Back" navigation. Allowed characters: [a-z0-9_]. Must be
     * unique among siblings.
     */
    public function key(): string;

    /**
     * Title shown in the body of the message that accompanies the keyboard.
     * Usually pulled from the translator (`$ctx->translator->translate(...)`).
     */
    public function title(MenuContext $ctx): string;

    /**
     * Optional secondary text rendered below the title. Returns null when the
     * screen has only a title + buttons.
     */
    public function body(MenuContext $ctx): ?string;

    /**
     * Button label shown on the PARENT screen when linking down to this node.
     * Distinct from `title()` because the link label is often shorter
     * (e.g. "📚 Categories" on the root → "Pick a category:" as the title).
     */
    public function buttonLabel(MenuContext $ctx): string;

    /**
     * Children of this node — rendered as the reply keyboard. May be static
     * (a fixed list) or dynamic (e.g. categories loaded from the host).
     *
     * Empty array means this node is a leaf — its `buttonLabel()` only ever
     * shows on the parent, and tapping it goes straight to `onSelected()`.
     *
     * @return iterable<MenuNode>
     */
    public function children(MenuContext $ctx): iterable;

    /**
     * Called when the user taps the button that leads to this node from its
     * parent screen.
     *
     * - Return `null` to render this node's children as the next screen
     *   (pure navigation node).
     * - Return a `MenuResult` to render that result as the next screen
     *   (action node — e.g. "Generate fresh post" returns a confirmation).
     */
    public function onSelected(MenuContext $ctx): ?MenuResult;
}

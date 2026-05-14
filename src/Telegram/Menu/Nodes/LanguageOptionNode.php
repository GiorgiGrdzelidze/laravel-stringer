<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Nodes;

use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;

/**
 * One button inside the LanguagePickerNode. Tapping it persists the chosen
 * locale and jumps back two levels up — out of the language picker and back
 * to whichever screen the user came from.
 *
 * Not meant to be instantiated outside `LanguagePickerNode::children()`.
 */
final class LanguageOptionNode implements MenuNode
{
    public function __construct(
        private readonly string $locale,
        private readonly ChatStateStore $state,
        private readonly string $label,
    ) {}

    public function key(): string
    {
        return $this->locale;
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('lang.saved', ['language' => $this->label]);
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $this->label;
    }

    public function children(MenuContext $ctx): iterable
    {
        return [];
    }

    public function onSelected(MenuContext $ctx): MenuResult
    {
        $this->state->setLanguage($ctx->chatId, $this->locale);

        // Jump back to the parent of the language picker (two segments up):
        // `root.settings.language.<code>` → `root.settings`. Falls back to
        // root if the path is unexpectedly shallow.
        $segments = explode('.', $ctx->currentPath);
        if (count($segments) >= 3) {
            $segments = array_slice($segments, 0, -2);
        } else {
            $segments = [$segments[0] ?? 'root'];
        }

        return MenuResult::navigate(implode('.', $segments));
    }
}

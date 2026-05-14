<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\KeyboardLayoutAware;
use Stringer\Laravel\Telegram\Menu\Nodes\LanguagePickerNode;

/**
 * Settings screen — currently exposes language switching. Future versions
 * will add notification preferences, default LLM driver overrides, etc.
 */
final class SettingsNode implements KeyboardLayoutAware, MenuNode
{
    public function __construct(
        private readonly ChatStateStore $state,
    ) {}

    public function key(): string
    {
        return 'settings';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.settings.title');
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.root.settings');
    }

    public function children(MenuContext $ctx): iterable
    {
        return [
            new LanguagePickerNode(['en', 'ka', 'ru'], $this->state),
        ];
    }

    public function onSelected(MenuContext $ctx): ?MenuResult
    {
        return null;
    }

    public function keyboardLayout(MenuContext $ctx): array
    {
        return [
            [$ctx->t('lang.button')],
            [$ctx->t('menu.back')],
        ];
    }
}

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
 * Root menu screen for Stringer. The entry point reached via /start and /menu.
 */
final class RootNode implements KeyboardLayoutAware, MenuNode
{
    public function __construct(
        private readonly ChatStateStore $state,
        private readonly TopicsNode $topicsNode,
        private readonly CategoriesNode $categoriesNode,
        private readonly GenerateNode $generateNode,
    ) {}

    public function key(): string
    {
        return 'root';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.root.title');
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
            $this->generateNode,
            $this->topicsNode,
            $this->categoriesNode,
            new SettingsNode($this->state),
            new HelpNode,
        ];
    }

    public function onSelected(MenuContext $ctx): ?MenuResult
    {
        return null;
    }

    public function keyboardLayout(MenuContext $ctx): array
    {
        return [
            [$ctx->t('stringer.root.generate')],
            [$ctx->t('stringer.root.topics'), $ctx->t('stringer.root.categories')],
            [$ctx->t('stringer.root.settings'), $ctx->t('stringer.root.help')],
        ];
    }

    /**
     * @return list<MenuNode>
     */
    public function languageMenuOptions(): array
    {
        return [new LanguagePickerNode(['en', 'ka', 'ru'], $this->state)];
    }
}

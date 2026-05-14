<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Nodes;

use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\KeyboardLayoutAware;

/**
 * Universal language-picker screen.
 *
 * Drop-in node any host's tree can include. Renders one button per locale
 * (resolved through the translator via `lang.name.<code>`) plus the standard
 * back button. Tapping a locale writes it to the chat state store and
 * navigates back to the previous screen — re-rendering it in the new
 * language.
 *
 * Hosts that want different button labels (custom flag emoji, fancier
 * formatting) can pass an override map in `$customLabels`.
 */
final class LanguagePickerNode implements KeyboardLayoutAware, MenuNode
{
    /**
     * @param  list<string>  $availableLocales  e.g. `['en', 'ka', 'ru']`
     * @param  array<string, string>  $customLabels  Optional locale → label override
     */
    public function __construct(
        private readonly array $availableLocales,
        private readonly ChatStateStore $state,
        private readonly array $customLabels = [],
    ) {}

    public function key(): string
    {
        return 'language';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('lang.title');
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('lang.button');
    }

    public function children(MenuContext $ctx): iterable
    {
        $children = [];
        foreach ($this->availableLocales as $locale) {
            $children[] = new LanguageOptionNode($locale, $this->state, $this->labelFor($locale, $ctx));
        }

        return $children;
    }

    public function onSelected(MenuContext $ctx): ?MenuResult
    {
        return null; // navigation node
    }

    public function keyboardLayout(MenuContext $ctx): array
    {
        $rows = [];
        foreach ($this->availableLocales as $locale) {
            $rows[] = [$this->labelFor($locale, $ctx)];
        }
        $rows[] = [$ctx->t('menu.back')];

        return $rows;
    }

    private function labelFor(string $locale, MenuContext $ctx): string
    {
        return $this->customLabels[$locale] ?? $ctx->t("lang.name.{$locale}");
    }
}

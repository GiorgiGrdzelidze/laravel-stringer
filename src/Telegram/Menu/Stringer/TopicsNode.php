<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\KeyboardLayoutAware;

/**
 * Recent topics screen. Lists the 10 most recent topics as a static text
 * block — tappable interactions on individual topics are deferred to a later
 * iteration (would require dynamic per-topic child nodes). For now hovers
 * the back button as the only action.
 */
final class TopicsNode implements KeyboardLayoutAware, MenuNode
{
    private const LIMIT = 10;

    private const HINT_TRUNCATE = 60;

    public function key(): string
    {
        return 'topics';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.topics.title');
    }

    public function body(MenuContext $ctx): string
    {
        $topics = BlogTopic::query()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        if ($topics->isEmpty()) {
            return $ctx->t('stringer.topics.empty');
        }

        $lines = [];
        foreach ($topics as $topic) {
            $hint = mb_strimwidth($topic->hint, 0, self::HINT_TRUNCATE, '…');
            $lines[] = $ctx->t('stringer.topics.entry', [
                'id' => $topic->id,
                'status' => $topic->status->value,
                'hint' => $hint,
            ]);
        }

        return implode("\n", $lines);
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.root.topics');
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

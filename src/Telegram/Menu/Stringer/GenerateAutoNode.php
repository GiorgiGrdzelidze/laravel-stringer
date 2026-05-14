<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;

/**
 * "🎲 Auto pick" — enqueues an Auto-source topic with no hint. The
 * AutoGenerateWeeklyJob's synthesis path (oldest queued / synthesize from
 * public content) is the same logic we reuse on demand.
 */
final class GenerateAutoNode implements MenuNode
{
    public function key(): string
    {
        return 'auto';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.generate.auto');
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.generate.auto');
    }

    public function children(MenuContext $ctx): iterable
    {
        return [];
    }

    public function onSelected(MenuContext $ctx): MenuResult
    {
        $topic = app(TopicQueue::class)->enqueue(
            hint: 'auto',
            source: TopicSource::Auto,
            chatId: $ctx->chatId,
        );

        GenerateDraftJob::dispatch($topic->id);

        return MenuResult::message(
            $ctx->t('stringer.generate.enqueued', ['id' => $topic->id]),
        );
    }
}

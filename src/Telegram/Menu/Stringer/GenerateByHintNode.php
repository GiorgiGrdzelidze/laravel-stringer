<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\PendingInputStore;

/**
 * "📝 By topic hint" — schedules a pending-input slot and asks the user to
 * send the hint as the next message. The webhook handler reads the slot and
 * dispatches `EnqueueWithHintAction` with the captured text.
 */
final class GenerateByHintNode implements MenuNode
{
    public const PENDING_ACTION_KEY = 'stringer.generate.hint';

    public function __construct(
        private readonly PendingInputStore $pendingInputs,
    ) {}

    public function key(): string
    {
        return 'hint';
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.generate.hint_prompt');
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.generate.hint');
    }

    public function children(MenuContext $ctx): iterable
    {
        return [];
    }

    public function onSelected(MenuContext $ctx): MenuResult
    {
        $this->pendingInputs->set(
            chatId: $ctx->chatId,
            expectedAction: self::PENDING_ACTION_KEY,
            payload: null,
            ttlSeconds: 60,
        );

        return MenuResult::message($ctx->t('stringer.generate.hint_prompt'));
    }
}

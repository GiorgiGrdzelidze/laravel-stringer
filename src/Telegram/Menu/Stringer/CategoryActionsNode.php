<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\KeyboardLayoutAware;
use Stringer\Laravel\Telegram\Menu\PendingInputStore;

/**
 * Per-category screen — reached by tapping a category from CategoriesNode.
 * Offers two leaf actions: fire generation immediately, or prompt for a
 * hint first.
 */
final class CategoryActionsNode implements KeyboardLayoutAware, MenuNode
{
    public const PENDING_PAYLOAD_KEY = 'category_slug';

    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        private readonly PendingInputStore $pendingInputs,
    ) {}

    public function key(): string
    {
        return $this->slug;
    }

    public function title(MenuContext $ctx): string
    {
        return $ctx->t('stringer.category.title', ['category' => $this->displayName]);
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $this->displayName;
    }

    public function children(MenuContext $ctx): iterable
    {
        return [
            new CategoryFireNode($this->displayName),
            new CategoryWithHintNode($this->slug, $this->pendingInputs),
        ];
    }

    public function onSelected(MenuContext $ctx): ?MenuResult
    {
        return null;
    }

    public function keyboardLayout(MenuContext $ctx): array
    {
        return [
            [$ctx->t('stringer.category.fire')],
            [$ctx->t('stringer.category.with_hint')],
            [$ctx->t('menu.back')],
        ];
    }
}

/**
 * Leaf node: "✅ Generate fresh post" inside a category. Enqueues + dispatches
 * the topic immediately.
 */
final class CategoryFireNode implements MenuNode
{
    public function __construct(
        private readonly string $displayName,
    ) {}

    public function key(): string
    {
        return 'fire';
    }

    public function title(MenuContext $ctx): string
    {
        return $this->displayName;
    }

    public function body(MenuContext $ctx): ?string
    {
        return null;
    }

    public function buttonLabel(MenuContext $ctx): string
    {
        return $ctx->t('stringer.category.fire');
    }

    public function children(MenuContext $ctx): iterable
    {
        return [];
    }

    public function onSelected(MenuContext $ctx): MenuResult
    {
        $topic = app(TopicQueue::class)->enqueue(
            hint: "Generate a fresh post in the {$this->displayName} category. Pick a sub-topic not covered by the recent posts shown in context.",
            source: TopicSource::Manual,
            chatId: $ctx->chatId,
        );

        GenerateDraftJob::dispatch($topic->id);

        return MenuResult::message(
            $ctx->t('stringer.generate.enqueued', ['id' => $topic->id]),
        );
    }
}

/**
 * Leaf node: "📝 With a hint first" inside a category. Schedules a pending
 * input — the next user message becomes the hint, scoped to this category.
 */
final class CategoryWithHintNode implements MenuNode
{
    public const PENDING_ACTION_KEY = 'stringer.category.hint';

    public function __construct(
        private readonly string $slug,
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
        return $ctx->t('stringer.category.with_hint');
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
            payload: [CategoryActionsNode::PENDING_PAYLOAD_KEY => $this->slug],
            ttlSeconds: 60,
        );

        return MenuResult::message($ctx->t('stringer.generate.hint_prompt'));
    }
}

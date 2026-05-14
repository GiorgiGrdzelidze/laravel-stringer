<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu;

use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuTranslator;
use Stringer\Laravel\Telegram\Menu\Stringer\CategoryActionsNode;
use Stringer\Laravel\Telegram\Menu\Stringer\CategoryWithHintNode;
use Stringer\Laravel\Telegram\Menu\Stringer\GenerateByHintNode;
use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;
use Throwable;

/**
 * Resolves the "next message after a menu prompt" by looking up the chat's
 * pending-input slot and routing the captured text to the matching action.
 *
 * Centralised here so the webhook controller stays a thin dispatcher and so
 * tests can exercise the resolver directly without needing the full HTTP
 * stack.
 *
 * Returns `true` when the resolver consumed the message (and the webhook
 * should stop processing), `false` otherwise.
 */
final class PendingInputResolver
{
    public function __construct(
        private readonly PendingInputStore $pendingInputs,
        private readonly ChatStateStore $state,
        private readonly TelegramClient $telegram,
        private readonly MenuTranslator $translator,
        private readonly string $defaultLocale = 'en',
    ) {}

    public function tryResolve(ParsedCommand $command): bool
    {
        $pending = $this->pendingInputs->consumeIfPresent($command->chatId);

        if ($pending === null) {
            return false;
        }

        $text = trim($command->text);
        if ($text === '') {
            return true; // consumed but ignored — user sent an empty message
        }

        $locale = $this->state->language($command->chatId, $this->defaultLocale);

        try {
            $this->routeToAction($pending['action'], $pending['payload'] ?? [], $command->chatId, $text, $locale);
        } catch (Throwable $e) {
            Log::channel('daily')->error('stringer.menu.pending_input.failed', [
                'action' => $pending['action'],
                'chat_id' => $command->chatId,
                'error' => $e->getMessage(),
            ]);

            $this->telegram->sendMessage(
                $command->chatId,
                $this->translator->translate('menu.error.generic', $locale),
            );
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function routeToAction(string $action, array $payload, int $chatId, string $hint, string $locale): void
    {
        match ($action) {
            GenerateByHintNode::PENDING_ACTION_KEY => $this->enqueueWithHint($chatId, $hint, $locale),
            CategoryWithHintNode::PENDING_ACTION_KEY => $this->enqueueCategoryHint($chatId, $hint, $payload, $locale),
            default => $this->telegram->sendMessage($chatId, $this->translator->translate('menu.error.generic', $locale)),
        };
    }

    private function enqueueWithHint(int $chatId, string $hint, string $locale): void
    {
        $topic = app(TopicQueue::class)->enqueue($hint, TopicSource::Manual, chatId: $chatId);
        GenerateDraftJob::dispatch($topic->id);

        $this->telegram->sendMessage(
            $chatId,
            $this->translator->translate('stringer.generate.enqueued', $locale, ['id' => $topic->id]),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enqueueCategoryHint(int $chatId, string $hint, array $payload, string $locale): void
    {
        $slug = (string) ($payload[CategoryActionsNode::PENDING_PAYLOAD_KEY] ?? '');
        $scopedHint = $slug !== ''
            ? "Generate a post in the {$slug} category. Operator hint: {$hint}"
            : $hint;

        $topic = app(TopicQueue::class)->enqueue($scopedHint, TopicSource::Manual, chatId: $chatId);
        GenerateDraftJob::dispatch($topic->id);

        $this->telegram->sendMessage(
            $chatId,
            $this->translator->translate('stringer.generate.enqueued', $locale, ['id' => $topic->id]),
        );
    }
}

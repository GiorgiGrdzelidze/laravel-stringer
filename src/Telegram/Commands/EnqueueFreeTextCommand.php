<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Commands;

use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;

/**
 * Fallback command for non-/ messages — treats the whole text as a
 * topic hint and enqueues it as a `Manual`-source topic.
 */
final class EnqueueFreeTextCommand implements Command
{
    public function __construct(
        private readonly TopicQueue $queue,
        private readonly TelegramClient $telegram,
    ) {}

    public function handle(ParsedCommand $command): void
    {
        $hint = trim($command->text);

        if ($hint === '') {
            return;
        }

        $topic = $this->queue->enqueue($hint, TopicSource::Manual, chatId: $command->chatId);

        $this->telegram->sendMessage($command->chatId, "თემა რიგში დაემატა #{$topic->id}.");
    }
}

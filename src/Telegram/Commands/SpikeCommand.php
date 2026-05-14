<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Commands;

use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;

/**
 * /spike {id} — reject a topic. Idempotent: rejecting an already-rejected
 * topic is a silent no-op (TopicQueue::markRejected just stamps the
 * status field).
 */
final class SpikeCommand implements Command
{
    public function __construct(
        private readonly TopicQueue $queue,
        private readonly TelegramClient $telegram,
    ) {}

    public function handle(ParsedCommand $command): void
    {
        $arg = trim($command->text);

        if (preg_match('/^\d+$/', $arg) !== 1) {
            $this->telegram->sendMessage($command->chatId, 'Usage: /spike {id}');

            return;
        }

        $topicId = (int) $arg;
        $topic = BlogTopic::query()->find($topicId);

        if (! $topic instanceof BlogTopic) {
            $this->telegram->sendMessage($command->chatId, "Topic #{$topicId} not found.");

            return;
        }

        $this->queue->markRejected($topic);

        $this->telegram->sendMessage($command->chatId, "Topic #{$topic->id} rejected.");
    }
}

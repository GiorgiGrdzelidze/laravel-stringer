<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Commands;

use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;

/**
 * /file or /draft — three behaviors depending on text:
 * - With an integer argument (`/file 42`) → force-draft topic #42 by
 *   dispatching GenerateDraftJob and acking.
 * - With free text (`/file write about queues`) → enqueue a new Manual
 *   topic and ack with its id.
 * - With no text and no argument (`/file`) → list pending (Queued) topics.
 */
final class FileCommand implements Command
{
    public function __construct(
        private readonly TopicQueue $queue,
        private readonly TelegramClient $telegram,
    ) {}

    public function handle(ParsedCommand $command): void
    {
        $arg = trim($command->text);

        if ($arg === '') {
            $this->listPending($command->chatId);

            return;
        }

        if (preg_match('/^\d+$/', $arg) === 1) {
            $this->forceDraft($command->chatId, (int) $arg);

            return;
        }

        $this->enqueueManual($command->chatId, $arg);
    }

    private function listPending(int $chatId): void
    {
        $pending = BlogTopic::query()
            ->where('status', TopicStatus::Queued->value)
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        if ($pending->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'მოლოდინში არცერთი თემა.');

            return;
        }

        $lines = ['მოლოდინში:'];
        foreach ($pending as $topic) {
            $hint = mb_strimwidth($topic->hint, 0, 60, '…');
            $lines[] = sprintf('#%d %s', $topic->id, $hint);
        }

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function forceDraft(int $chatId, int $topicId): void
    {
        $topic = BlogTopic::query()->find($topicId);

        if (! $topic instanceof BlogTopic) {
            $this->telegram->sendMessage($chatId, "თემა #{$topicId} ვერ მოიძებნა.");

            return;
        }

        GenerateDraftJob::dispatch($topic->id);

        $this->telegram->sendMessage($chatId, "გენერაცია ჩაშვებულია #{$topic->id}.");
    }

    private function enqueueManual(int $chatId, string $hint): void
    {
        $topic = $this->queue->enqueue($hint, TopicSource::Manual, chatId: $chatId);

        $this->telegram->sendMessage($chatId, "თემა რიგში დაემატა #{$topic->id}.");
    }
}

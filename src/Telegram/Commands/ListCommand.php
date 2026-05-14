<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Commands;

use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;

final class ListCommand implements Command
{
    private const LIMIT = 10;

    private const HINT_TRUNCATE = 60;

    public function __construct(
        private readonly TelegramClient $telegram,
    ) {}

    public function handle(ParsedCommand $command): void
    {
        $topics = BlogTopic::query()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        if ($topics->isEmpty()) {
            $this->telegram->sendMessage($command->chatId, 'No topics yet.');

            return;
        }

        $lines = ['Recent topics:'];
        foreach ($topics as $topic) {
            $hint = mb_strimwidth($topic->hint, 0, self::HINT_TRUNCATE, '…');
            $lines[] = sprintf('#%d [%s] %s', $topic->id, $topic->status->value, $hint);
        }

        $this->telegram->sendMessage($command->chatId, implode("\n", $lines));
    }
}

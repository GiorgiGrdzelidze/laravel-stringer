<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Commands;

use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;

final class HelpCommand implements Command
{
    private const MESSAGE = <<<'TEXT'
Commands:

/list — last 10 topics
/generate — list pending topics, or pass free text to enqueue a new one
/generate {id} — force draft generation for topic {id}
/spike {id} — reject topic {id}
/help — this message
TEXT;

    public function __construct(
        private readonly TelegramClient $telegram,
    ) {}

    public function handle(ParsedCommand $command): void
    {
        $this->telegram->sendMessage($command->chatId, self::MESSAGE);
    }
}

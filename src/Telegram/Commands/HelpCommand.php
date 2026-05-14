<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Commands;

use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;

final class HelpCommand implements Command
{
    private const MESSAGE = <<<'TEXT'
ბრძანებები / commands:

/list — ბოლო 10 თემა
/file — დააფაილე ან გადახედე (free text → ახალი თემა)
/file {id} — ერთი თემის ფორსირებული გენერაცია
/spike {id} — თემის უარყოფა
/help — ეს შეტყობინება
TEXT;

    public function __construct(
        private readonly TelegramClient $telegram,
    ) {}

    public function handle(ParsedCommand $command): void
    {
        $this->telegram->sendMessage($command->chatId, self::MESSAGE);
    }
}

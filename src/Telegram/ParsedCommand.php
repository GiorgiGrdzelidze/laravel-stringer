<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram;

/**
 * Distilled shape of a Telegram update — what the dispatcher actually
 * cares about.
 *
 * - `command` is the leading slash word lowercased (e.g. `/Help` → `help`),
 *   or null when the message has no command prefix.
 * - `text` is the remaining body after the command + space, or the
 *   whole message body when there's no command.
 * - `chatId` is the originating chat id (used both for the allowlist
 *   check upstream and as the reply target).
 */
final readonly class ParsedCommand
{
    public function __construct(
        public ?string $command,
        public string $text,
        public int $chatId,
        public ?int $messageId = null,
    ) {}

    public function isCommand(): bool
    {
        return $this->command !== null;
    }
}

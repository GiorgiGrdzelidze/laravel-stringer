<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram;

use InvalidArgumentException;

/**
 * Parses a raw Telegram update payload into a `ParsedCommand`.
 *
 * Telegram updates come in many shapes (message, edited_message,
 * callback_query, etc.); v0.1.0 only handles `message.text`. Anything
 * without a usable text body throws — the controller catches and
 * responds 200 OK so Telegram doesn't retry.
 */
final class UpdateParser
{
    /**
     * @param  array<string, mixed>  $update
     */
    public function parse(array $update): ParsedCommand
    {
        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            throw new InvalidArgumentException('Telegram update has no `message` field.');
        }

        $text = $message['text'] ?? null;
        if (! is_string($text) || $text === '') {
            throw new InvalidArgumentException('Telegram message has no `text`.');
        }

        $chat = $message['chat'] ?? null;
        if (! is_array($chat) || ! isset($chat['id']) || ! is_numeric($chat['id'])) {
            throw new InvalidArgumentException('Telegram message has no `chat.id`.');
        }

        [$command, $remainder] = $this->extractCommand($text);

        return new ParsedCommand(
            command: $command,
            text: $remainder,
            chatId: (int) $chat['id'],
        );
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function extractCommand(string $text): array
    {
        if ($text === '' || $text[0] !== '/') {
            return [null, $text];
        }

        // Strip leading slash, optional @bot_handle, and split on first whitespace.
        $body = substr($text, 1);

        if (preg_match('/^(\w+)(?:@\w+)?(?:\s+(.*))?$/s', $body, $matches) !== 1) {
            return [null, $text];
        }

        $command = strtolower($matches[1]);
        $remainder = trim($matches[2] ?? '');

        return [$command, $remainder];
    }
}

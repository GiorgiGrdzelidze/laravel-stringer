<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Telegram Bot API.
 *
 * Two methods are enough for the v0.1.0 command surface — sendMessage
 * (every command reply) and editMessageText (used by `/list` to update
 * its message after pagination — reserved for v0.2 / out of scope here
 * but the shape is here so commands can grow into it).
 */
final class TelegramClient
{
    public function __construct(
        private readonly string $botToken,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(int $chatId, string $text): array
    {
        $this->requireBotToken();

        $response = Http::asJson()
            ->post($this->endpoint('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $text,
            ])
            ->throw();

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function editMessageText(int $chatId, int $messageId, string $text): array
    {
        $this->requireBotToken();

        $response = Http::asJson()
            ->post($this->endpoint('editMessageText'), [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
            ])
            ->throw();

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    private function endpoint(string $method): string
    {
        return 'https://api.telegram.org/bot'.$this->botToken.'/'.$method;
    }

    private function requireBotToken(): void
    {
        if ($this->botToken === '') {
            throw new RuntimeException(
                'TelegramClient has no bot token configured. Set STRINGER_TELEGRAM_BOT_TOKEN.'
            );
        }
    }
}

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
     * @param  'HTML'|'MarkdownV2'|null  $parseMode  Optional Telegram parse mode for rich formatting.
     * @param  array<string, mixed>|null  $replyMarkup  Optional Telegram reply_markup payload —
     *                                                  typically the output of
     *                                                  `ReplyKeyboard::toArray()` for a custom
     *                                                  keyboard, or `['remove_keyboard' => true]`
     *                                                  to dismiss an active one.
     * @return array<string, mixed>
     */
    public function sendMessage(
        int $chatId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
    ): array {
        $this->requireBotToken();

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
            $payload['disable_web_page_preview'] = true;
        }

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $response = Http::asJson()
            ->post($this->endpoint('sendMessage'), $payload)
            ->throw();

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * Send a photo to a chat. `$photoSource` may be either a publicly
     * reachable URL (Telegram fetches it server-side) or an absolute
     * local file path (we upload the bytes via multipart). Local paths
     * are the right call for self-hosted media that's not exposed
     * publicly, e.g. cover images stored in Spatie media library on a
     * dev or behind-a-firewall server.
     *
     * @param  array<string, mixed>  $replyMarkup
     * @return array<string, mixed>
     */
    public function sendPhoto(
        int $chatId,
        string $photoSource,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
    ): array {
        $this->requireBotToken();

        $payload = [
            'chat_id' => (string) $chatId,
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        if (is_file($photoSource)) {
            $bytes = file_get_contents($photoSource);
            if ($bytes === false) {
                throw new RuntimeException("Could not read photo file at {$photoSource}.");
            }

            $response = Http::attach('photo', $bytes, basename($photoSource))
                ->asMultipart()
                ->post($this->endpoint('sendPhoto'), $payload)
                ->throw();
        } else {
            $payload['photo'] = $photoSource;

            $response = Http::asJson()
                ->post($this->endpoint('sendPhoto'), $payload)
                ->throw();
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendLocation(int $chatId, float $latitude, float $longitude, ?string $caption = null): array
    {
        $this->requireBotToken();

        $response = Http::asJson()
            ->post($this->endpoint('sendLocation'), array_filter([
                'chat_id' => $chatId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'horizontal_accuracy' => null,
            ], static fn (mixed $v): bool => $v !== null))
            ->throw();

        if ($caption !== null && $caption !== '') {
            $this->sendMessage($chatId, $caption);
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * @param  list<string>  $photoUrls
     * @return array<string, mixed>
     */
    public function sendMediaGroup(int $chatId, array $photoUrls, ?string $caption = null): array
    {
        $this->requireBotToken();

        $media = [];
        foreach ($photoUrls as $index => $url) {
            $entry = ['type' => 'photo', 'media' => $url];
            if ($index === 0 && $caption !== null && $caption !== '') {
                $entry['caption'] = $caption;
            }
            $media[] = $entry;
        }

        $response = Http::asJson()
            ->post($this->endpoint('sendMediaGroup'), [
                'chat_id' => $chatId,
                'media' => $media,
            ])
            ->throw();

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * Register the bot's command list — what shows up in the blue "menu"
     * button next to the chat input. One call per language; per-language
     * descriptions are picked by Telegram based on the user's app locale.
     *
     * @param  list<array{command: string, description: string}>  $commands
     * @return array<string, mixed>
     */
    public function setMyCommands(array $commands, ?string $languageCode = null): array
    {
        $this->requireBotToken();

        $payload = ['commands' => $commands];
        if ($languageCode !== null && $languageCode !== '') {
            $payload['language_code'] = $languageCode;
        }

        $response = Http::asJson()
            ->post($this->endpoint('setMyCommands'), $payload)
            ->throw();

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * Best-effort message deletion. Returns true on success, false on any
     * Telegram error (e.g. bot lacks "Delete messages" admin permission in
     * the chat, message too old, etc.).
     */
    public function deleteMessage(int $chatId, int $messageId): bool
    {
        if ($this->botToken === '') {
            return false;
        }

        try {
            $response = Http::asJson()
                ->post($this->endpoint('deleteMessage'), [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
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

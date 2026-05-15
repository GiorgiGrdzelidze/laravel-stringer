<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu;

use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;
use Stringer\Laravel\Telegram\Menu\DataObjects\ReplyKeyboard;
use Stringer\Laravel\Telegram\TelegramClient;

/**
 * Turns a `MenuResult` (or a `MenuNode` to render directly) into the actual
 * Telegram API calls.
 *
 * Owns three concerns the rest of the menu code stays innocent of:
 *
 *  1. **Reply-keyboard composition** — given a `MenuNode`, generate the
 *     keyboard from its children's button labels in row layout. Default
 *     layout is two-per-row; nodes can override by implementing
 *     `KeyboardLayoutAware`.
 *  2. **Long-message splitting** — Telegram caps message text at 4096
 *     characters. Renderer auto-splits at paragraph boundaries and only
 *     attaches the keyboard to the last message in the chain.
 *  3. **MenuResult kind dispatch** — chooses `sendMessage` / `sendPhoto` /
 *     `sendLocation` / `sendMediaGroup` based on the result variant.
 */
final class MenuRenderer
{
    private const TELEGRAM_MESSAGE_CHAR_LIMIT = 4096;

    private const DEFAULT_BUTTONS_PER_ROW = 2;

    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly ChatStateStore $state,
    ) {}

    /**
     * Render a screen — the menu node's title/body plus its children as a
     * reply keyboard. Called by the router on navigation.
     */
    public function renderNode(MenuNode $node, MenuContext $ctx): void
    {
        $title = $node->title($ctx);
        $body = $node->body($ctx);
        $text = $body !== null && $body !== '' ? $title."\n\n".$body : $title;

        $keyboard = $this->keyboardFromChildren($node, $ctx);

        $this->sendText($ctx->chatId, $text, $keyboard);
    }

    /**
     * Render a `MenuResult` returned by an action.
     *
     * Note: `KIND_NAVIGATE` is intentionally NOT handled here — the router
     * takes care of that path. By the time a `MenuResult` reaches the
     * renderer it's already a terminal variant.
     */
    public function renderResult(MenuResult $result, MenuContext $ctx, ?MenuNode $fallbackNode = null): void
    {
        match ($result->kind) {
            MenuResult::KIND_MESSAGE => $this->renderMessage($result, $ctx, $fallbackNode),
            MenuResult::KIND_PHOTO => $this->renderPhoto($result, $ctx, $fallbackNode),
            MenuResult::KIND_LOCATION => $this->renderLocation($result, $ctx),
            MenuResult::KIND_ALBUM => $this->renderAlbum($result, $ctx),
            MenuResult::KIND_DISMISS => $this->renderDismiss($result, $ctx),
            default => null, // KIND_NAVIGATE handled by router; unknown kinds are no-ops.
        };
    }

    private function renderMessage(MenuResult $result, MenuContext $ctx, ?MenuNode $fallbackNode): void
    {
        /** @var string $text */
        $text = $result->payload['text'];
        /** @var ReplyKeyboard|null $keyboard */
        $keyboard = $result->payload['keyboard'] ?? null;

        $keyboard ??= $fallbackNode !== null ? $this->keyboardFromChildren($fallbackNode, $ctx) : null;

        $this->sendText($ctx->chatId, $text, $keyboard);
    }

    private function renderPhoto(MenuResult $result, MenuContext $ctx, ?MenuNode $fallbackNode): void
    {
        /** @var string $url */
        $url = $result->payload['url'];
        /** @var string|null $caption */
        $caption = $result->payload['caption'] ?? null;
        /** @var ReplyKeyboard|null $keyboard */
        $keyboard = $result->payload['keyboard'] ?? null;

        $keyboard ??= $fallbackNode !== null ? $this->keyboardFromChildren($fallbackNode, $ctx) : null;

        $this->telegram->sendPhoto(
            $ctx->chatId,
            $url,
            $caption,
            null,
            $keyboard?->toArray(),
        );
    }

    private function renderLocation(MenuResult $result, MenuContext $ctx): void
    {
        /** @var float $lat */
        $lat = $result->payload['lat'];
        /** @var float $lng */
        $lng = $result->payload['lng'];
        /** @var string|null $caption */
        $caption = $result->payload['caption'] ?? null;

        $this->telegram->sendLocation($ctx->chatId, $lat, $lng, $caption);
    }

    private function renderAlbum(MenuResult $result, MenuContext $ctx): void
    {
        /** @var list<string> $urls */
        $urls = $result->payload['urls'];
        /** @var string|null $caption */
        $caption = $result->payload['caption'] ?? null;

        $this->telegram->sendMediaGroup($ctx->chatId, $urls, $caption);
    }

    private function renderDismiss(MenuResult $result, MenuContext $ctx): void
    {
        /** @var string|null $text */
        $text = $result->payload['text'];

        $this->telegram->sendMessage(
            $ctx->chatId,
            $text ?? '👋',
            replyMarkup: ['remove_keyboard' => true],
        );
    }

    /**
     * Send a possibly-long text. Splits at paragraph boundaries; only the
     * LAST chunk carries the keyboard so the keyboard "anchors" to the
     * bottom of the chat.
     *
     * Before sending, deletes the bot's previous menu message for this chat
     * (if any). After sending, stores the last chunk's message_id as the new
     * "current screen" so the next render can clean it up in turn. Net
     * effect: the chat shows a single live bot screen, no scroll history.
     */
    private function sendText(int $chatId, string $text, ?ReplyKeyboard $keyboard): void
    {
        $previousId = $this->state->lastMenuMessageId($chatId);
        if ($previousId !== null) {
            $this->telegram->deleteMessage($chatId, $previousId);
        }

        $chunks = $this->splitForTelegram($text);
        $last = count($chunks) - 1;
        $lastMessageId = null;

        foreach ($chunks as $index => $chunk) {
            $response = $this->telegram->sendMessage(
                $chatId,
                $chunk,
                replyMarkup: $index === $last ? $keyboard?->toArray() : null,
            );

            if ($index === $last) {
                $messageId = $response['result']['message_id'] ?? null;
                if (is_int($messageId)) {
                    $lastMessageId = $messageId;
                }
            }
        }

        $this->state->setLastMenuMessageId($chatId, $lastMessageId);
    }

    /**
     * Split a message at paragraph boundaries to stay under Telegram's 4096
     * character limit. Best-effort: very long single paragraphs are hard-cut
     * at the limit (rare in practice for menu titles + bodies).
     *
     * @return list<string>
     */
    private function splitForTelegram(string $text): array
    {
        if (mb_strlen($text) <= self::TELEGRAM_MESSAGE_CHAR_LIMIT) {
            return [$text];
        }

        $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;
            if (mb_strlen($candidate) <= self::TELEGRAM_MESSAGE_CHAR_LIMIT) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
                $current = '';
            }

            if (mb_strlen($paragraph) <= self::TELEGRAM_MESSAGE_CHAR_LIMIT) {
                $current = $paragraph;

                continue;
            }

            // Single paragraph too long — hard split.
            foreach (mb_str_split($paragraph, self::TELEGRAM_MESSAGE_CHAR_LIMIT) as $piece) {
                $chunks[] = $piece;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Build a reply keyboard from a node's children, two buttons per row by
     * default. Nodes that want a custom layout can implement
     * `KeyboardLayoutAware` and return their own rows.
     */
    private function keyboardFromChildren(MenuNode $node, MenuContext $ctx): ?ReplyKeyboard
    {
        if ($node instanceof KeyboardLayoutAware) {
            $rows = $node->keyboardLayout($ctx);

            return $rows === [] ? null : new ReplyKeyboard($rows);
        }

        $labels = [];
        foreach ($node->children($ctx) as $child) {
            $labels[] = $child->buttonLabel($ctx);
        }

        if ($labels === []) {
            return null;
        }

        $rows = array_chunk($labels, self::DEFAULT_BUTTONS_PER_ROW);

        return new ReplyKeyboard($rows);
    }
}

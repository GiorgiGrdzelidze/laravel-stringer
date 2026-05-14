<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu;

use Stringer\Laravel\Models\TelegramChatState;
use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;

/**
 * Default `ChatStateStore` — backs onto the `telegram_chat_states` table.
 *
 * Lazy row creation: a row is created the first time a chat writes anything
 * (language change, path navigation). Reads against a non-existent row
 * return the supplied fallback.
 */
final class EloquentChatStateStore implements ChatStateStore
{
    public function language(int $chatId, string $fallback): string
    {
        $state = TelegramChatState::query()->find($chatId);

        return $state instanceof TelegramChatState ? $state->language : $fallback;
    }

    public function setLanguage(int $chatId, string $language): void
    {
        TelegramChatState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['language' => $language],
        );
    }

    public function currentPath(int $chatId): string
    {
        $state = TelegramChatState::query()->find($chatId);

        return $state instanceof TelegramChatState ? $state->current_path : 'root';
    }

    public function setCurrentPath(int $chatId, string $path): void
    {
        TelegramChatState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['current_path' => $path],
        );
    }

    public function lastMenuMessageId(int $chatId): ?int
    {
        $state = TelegramChatState::query()->find($chatId);

        return $state instanceof TelegramChatState ? $state->last_menu_message_id : null;
    }

    public function setLastMenuMessageId(int $chatId, ?int $messageId): void
    {
        TelegramChatState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['last_menu_message_id' => $messageId],
        );
    }
}

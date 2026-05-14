<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Contracts;

/**
 * Persists per-chat menu state — language preference and current path in the
 * menu tree.
 *
 * Default implementation (`EloquentChatStateStore`) uses the
 * `telegram_chat_states` table. Hosts that prefer a cache-only store can
 * implement this interface against Redis / a session driver.
 */
interface ChatStateStore
{
    /**
     * Read the current language for a chat, or fall back to the operator's
     * configured default if no row exists yet.
     */
    public function language(int $chatId, string $fallback): string;

    public function setLanguage(int $chatId, string $language): void;

    /**
     * Read the path of the screen the chat is currently on. Returns `'root'`
     * when no row exists.
     */
    public function currentPath(int $chatId): string;

    public function setCurrentPath(int $chatId, string $path): void;

    /**
     * Telegram message id of the most recent menu-screen message the bot
     * sent to this chat. The renderer deletes it before sending a new
     * screen so the chat shows a single live bot message instead of a
     * history of every navigation step. Returns null when nothing has
     * been sent yet.
     */
    public function lastMenuMessageId(int $chatId): ?int;

    public function setLastMenuMessageId(int $chatId, ?int $messageId): void;
}

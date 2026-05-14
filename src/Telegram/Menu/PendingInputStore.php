<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu;

use Stringer\Laravel\Models\TelegramPendingInput;

/**
 * Per-chat "waiting for next message" buffer with TTL.
 *
 * Powers the "type your hint as the next message" flow:
 *
 *  1. A menu node calls `set($chatId, 'stringer.generate.hint', ttl: 60)`.
 *  2. The router asks `consumeIfPresent($chatId)` on the next inbound text.
 *  3. If a non-expired row exists, the row is deleted and the expected
 *     action key is returned — the host dispatcher then routes the text
 *     into the matching action.
 *
 * One row per chat by design — issuing a new prompt overwrites any in-flight
 * one. Expired rows are skipped on read and cleaned by `cleanupExpired()`.
 */
final class PendingInputStore
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function set(int $chatId, string $expectedAction, ?array $payload = null, int $ttlSeconds = 60): void
    {
        TelegramPendingInput::query()->updateOrCreate(
            ['chat_id' => $chatId],
            [
                'expected_action' => $expectedAction,
                'payload' => $payload,
                'expires_at' => now()->addSeconds($ttlSeconds),
                'created_at' => now(),
            ],
        );
    }

    /**
     * Look up + delete the chat's pending input. Returns `null` when there's
     * nothing pending OR when the row has expired (expired rows are deleted
     * silently).
     *
     * @return array{action: string, payload: array<string, mixed>|null}|null
     */
    public function consumeIfPresent(int $chatId): ?array
    {
        $row = TelegramPendingInput::query()->find($chatId);

        if (! $row instanceof TelegramPendingInput) {
            return null;
        }

        $action = $row->expected_action;
        $payload = $row->payload;
        $expired = $row->isExpired();

        $row->delete();

        if ($expired) {
            return null;
        }

        return ['action' => $action, 'payload' => $payload];
    }

    /**
     * Delete every expired row. Safe to call from a scheduled task.
     */
    public function cleanupExpired(): int
    {
        return TelegramPendingInput::query()
            ->where('expires_at', '<', now())
            ->delete();
    }
}

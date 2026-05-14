<?php

declare(strict_types=1);

namespace Stringer\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Telegram\CommandDispatcher;
use Stringer\Laravel\Telegram\Menu\MenuRouter;
use Stringer\Laravel\Telegram\Menu\PendingInputResolver;
use Stringer\Laravel\Telegram\ParsedCommand;
use Stringer\Laravel\Telegram\TelegramClient;
use Stringer\Laravel\Telegram\UpdateParser;
use Throwable;

/**
 * Single-action controller for `POST /webhooks/telegram/{secret}`.
 *
 * Order of dispatch for an inbound message:
 *
 *   1. **Pending input** — if the chat has a pending-input row, the text is
 *      consumed by the matching `PendingInputAction` (e.g. the "send your
 *      hint" flow inside the menu). Highest priority — outcompetes both the
 *      menu and the legacy command dispatcher.
 *   2. **Menu router** — if the text matches a button in the chat's current
 *      menu screen, the router navigates or fires the action. Returns true on
 *      handled, false on no match.
 *   3. **Legacy command dispatcher** — the `/help`, `/list`, `/generate`,
 *      `/spike` text commands continue to work for power users who skip the
 *      menu entirely.
 *
 * Errors at every layer are logged to the daily channel and swallowed —
 * Telegram retries non-2xx responses, which we don't want for malformed
 * updates or downstream failures.
 */
final class TelegramWebhookController
{
    public function __construct(
        private readonly UpdateParser $parser,
        private readonly CommandDispatcher $dispatcher,
        private readonly MenuRouter $menuRouter,
        private readonly PendingInputResolver $pendingInputs,
        private readonly TelegramClient $telegram,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $update */
            $update = $request->json()->all();
            $parsed = $this->parser->parse($update);

            // 1. Pending input — captures the next message from a chat that
            //    was prompted via the menu.
            if ($parsed->command === null && $this->pendingInputs->tryResolve($parsed)) {
                $this->cleanUpTapMessage($parsed);

                return new JsonResponse(['ok' => true]);
            }

            // 2. Menu router — match against the chat's current screen.
            $handled = $this->menuRouter->handle(
                chatId: $parsed->chatId,
                userId: $parsed->chatId, // No distinct user id in our payload shape; chat id is fine.
                messageText: $parsed->command !== null ? '/'.$parsed->command.($parsed->text !== '' ? ' '.$parsed->text : '') : $parsed->text,
            );

            if ($handled) {
                $this->cleanUpTapMessage($parsed);

                return new JsonResponse(['ok' => true]);
            }

            // 3. Legacy text commands. We leave the user's message visible
            //    here — it's free-form text (or an explicit slash command),
            //    not a button tap.
            $this->dispatcher->dispatch($parsed);
        } catch (Throwable $e) {
            Log::channel('daily')->error('stringer.telegram_webhook.failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Delete the user's incoming message after the menu has handled it, so
     * the chat history only shows the bot's screens — not every button tap.
     * Best-effort: silently no-ops if the bot lacks delete permission, the
     * message is too old (>48 h), or message_id wasn't in the update.
     */
    private function cleanUpTapMessage(ParsedCommand $parsed): void
    {
        if ($parsed->messageId === null) {
            return;
        }

        $this->telegram->deleteMessage($parsed->chatId, $parsed->messageId);
    }
}

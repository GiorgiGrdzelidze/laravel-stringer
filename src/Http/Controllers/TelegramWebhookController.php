<?php

declare(strict_types=1);

namespace Stringer\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Telegram\CommandDispatcher;
use Stringer\Laravel\Telegram\UpdateParser;
use Throwable;

/**
 * Single-action controller for `POST /webhooks/telegram/{secret}`.
 *
 * Receives raw Telegram update payloads, parses them, dispatches to the
 * matching command handler, and always acks 200 OK regardless of internal
 * outcome — Telegram retries non-2xx responses, which we don't want for
 * malformed updates or downstream failures (operator can re-issue a
 * `/file {id}` instead).
 *
 * Errors are logged to `daily` before being swallowed so a missing bot
 * token, a malformed update, or a downstream throw doesn't disappear
 * silently — the operator gets a daily-log breadcrumb to start from.
 */
final class TelegramWebhookController
{
    public function __construct(
        private readonly UpdateParser $parser,
        private readonly CommandDispatcher $dispatcher,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $update */
            $update = $request->json()->all();
            $parsed = $this->parser->parse($update);
            $this->dispatcher->dispatch($parsed);
        } catch (Throwable $e) {
            Log::channel('daily')->error('stringer.telegram_webhook.failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        return new JsonResponse(['ok' => true]);
    }
}

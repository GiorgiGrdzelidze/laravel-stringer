<?php

declare(strict_types=1);

namespace Stringer\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-layer auth for the Telegram webhook:
 *
 * 1. URL secret — compared via `hash_equals` against
 *    `config('stringer.telegram.secret')`. Mismatch returns **404** so
 *    a leaked URL prefix can't be probed for "is this the bot here".
 * 2. Chat-id allowlist — `config('stringer.telegram.allowed_chat_ids')`.
 *    A non-allowlisted chat gets a **200** with empty body so Telegram
 *    doesn't retry the same update indefinitely.
 *
 * The optional `X-Telegram-Bot-Api-Secret-Token` header check (extra
 * Telegram-recommended layer) is intentionally not enforced in v0.1.0
 * — the URL secret is the primary auth and reading from a request
 * header that grdzelo doesn't necessarily set on Telegram's side is
 * overkill for this surface.
 */
final class VerifyTelegramSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $request->route('secret');
        $expected = (string) config('stringer.telegram.secret', '');

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            return response('', 404);
        }

        $chatId = $this->extractChatId($request);
        if ($chatId === null) {
            // Malformed payload — ack with 200 so Telegram drops the update.
            return response('', 200);
        }

        /** @var list<int> $allowed */
        $allowed = (array) config('stringer.telegram.allowed_chat_ids', []);

        if (! in_array($chatId, $allowed, true)) {
            return response('', 200);
        }

        return $next($request);
    }

    private function extractChatId(Request $request): ?int
    {
        $update = $request->json()->all();
        if (! is_array($update)) {
            return null;
        }

        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $chat = $message['chat'] ?? null;
        if (! is_array($chat) || ! isset($chat['id']) || ! is_numeric($chat['id'])) {
            return null;
        }

        return (int) $chat['id'];
    }
}

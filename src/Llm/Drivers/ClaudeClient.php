<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Claude driver against `api.anthropic.com`.
 *
 * Minimal implementation — sufficient for v0.1.0 driver-swap parity.
 * Default model: `claude-sonnet-4-5` (overridable via `CLAUDE_MODEL`).
 */
final class ClaudeClient extends AbstractLlmClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const MAX_TOKENS = 4096;

    protected function request(string $message): string
    {
        $this->requireApiKey();

        $response = Http::asJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
            ->post(self::ENDPOINT, [
                'model' => $this->model,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    ['role' => 'user', 'content' => $message],
                ],
            ])
            ->throw();

        $text = data_get($response->json(), 'content.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Claude returned an empty or malformed response.');
        }

        return $text;
    }
}

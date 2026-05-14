<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI driver against `api.openai.com/v1/chat/completions`.
 *
 * Minimal implementation — sufficient for v0.1.0 driver-swap parity.
 * Default model: `gpt-4o-mini` (overridable via `OPENAI_MODEL`).
 */
final class OpenAiClient extends AbstractLlmClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    protected function request(string $message): string
    {
        $this->requireApiKey();

        $response = Http::asJson()
            ->withToken($this->apiKey)
            ->post(self::ENDPOINT, [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $message],
                ],
            ])
            ->throw();

        $text = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('OpenAI returned an empty or malformed response.');
        }

        return $text;
    }
}

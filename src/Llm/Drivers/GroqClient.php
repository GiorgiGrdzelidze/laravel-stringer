<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Groq driver against `api.groq.com/openai/v1/chat/completions`.
 *
 * Groq exposes an OpenAI-compatible endpoint, so the request/response
 * shape matches `OpenAiClient`. Minimal implementation for v0.1.0.
 * Default model: `llama-3.3-70b-versatile` (overridable via `GROQ_MODEL`).
 */
final class GroqClient extends AbstractLlmClient
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    protected function request(string $message): string
    {
        $this->requireApiKey();

        $response = Http::asJson()
            ->timeout($this->timeoutSeconds)
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
            throw new RuntimeException('Groq returned an empty or malformed response.');
        }

        return $text;
    }
}

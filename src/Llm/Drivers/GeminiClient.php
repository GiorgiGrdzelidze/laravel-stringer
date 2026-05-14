<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini driver against `generativelanguage.googleapis.com`.
 *
 * Default model: `gemini-2.0-flash` (overridable via `GEMINI_MODEL`).
 * Auth: API key in the `key` query string parameter.
 */
final class GeminiClient extends AbstractLlmClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    protected function request(string $message): string
    {
        $this->requireApiKey();

        $response = Http::asJson()
            ->withQueryParameters(['key' => $this->apiKey])
            ->post(sprintf(self::ENDPOINT, $this->model), [
                'contents' => [[
                    'parts' => [['text' => $message]],
                    'role' => 'user',
                ]],
            ])
            ->throw();

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini returned an empty or malformed response.');
        }

        return $text;
    }
}

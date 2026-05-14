<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm;

use InvalidArgumentException;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Llm\Drivers\ClaudeClient;
use Stringer\Laravel\Llm\Drivers\GeminiClient;
use Stringer\Laravel\Llm\Drivers\GroqClient;
use Stringer\Laravel\Llm\Drivers\OpenAiClient;

/**
 * Resolves the configured LLM driver into an `LlmClient`.
 *
 * Driver name comes from `config('stringer.llm.driver')`; the matching
 * API key and model id come from `config('stringer.llm.api_keys.*')`
 * and `config('stringer.llm.models.*')`.
 *
 * Bound as a singleton; `make()` returns a fresh driver instance each
 * time so a host can swap drivers at runtime by re-resolving.
 */
final class LlmManager
{
    public function __construct(
        /** @var array{driver: string, api_keys: array<string, ?string>, models: array<string, string>} */
        private readonly array $config,
    ) {}

    public function make(): LlmClient
    {
        $driver = $this->config['driver'] ?? '';
        $apiKey = (string) ($this->config['api_keys'][$driver] ?? '');
        $model = (string) ($this->config['models'][$driver] ?? '');

        return match ($driver) {
            'gemini' => new GeminiClient($apiKey, $model),
            'claude' => new ClaudeClient($apiKey, $model),
            'openai' => new OpenAiClient($apiKey, $model),
            'groq' => new GroqClient($apiKey, $model),
            default => throw new InvalidArgumentException(
                "Unknown LLM driver '{$driver}'. Supported: gemini, claude, openai, groq."
            ),
        };
    }
}

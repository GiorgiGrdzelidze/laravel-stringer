<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
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
 * and `config('stringer.llm.models.*')`. Config is read inside `make()`
 * (not snapshotted at construction) so runtime `config()->set(...)`
 * swaps take effect on the next resolve — useful for tests and for
 * long-running workers that switch drivers via a control plane.
 */
class LlmManager
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function make(): LlmClient
    {
        $driver = (string) $this->config->get('stringer.llm.driver', '');
        $apiKey = (string) $this->config->get("stringer.llm.api_keys.{$driver}", '');
        $model = $this->modelName();

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

    /**
     * Returns the model identifier the configured driver will use — handy
     * for recording on `BlogTopic.generated_by` after a successful draft.
     */
    public function modelName(): string
    {
        $driver = (string) $this->config->get('stringer.llm.driver', '');

        return (string) $this->config->get("stringer.llm.models.{$driver}", '');
    }
}

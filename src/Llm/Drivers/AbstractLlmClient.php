<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm\Drivers;

use RuntimeException;
use Stringer\Laravel\Contracts\LlmClient;

/**
 * Shared scaffolding for the four built-in LLM drivers.
 *
 * `draft()` and `translate()` are thin pass-throughs to `request()`: the
 * argument they receive IS the final prompt string, produced by the
 * `PromptBuilder` upstream in the pipeline. The two methods stay distinct
 * on the interface so future driver implementations can route them to
 * different model overrides (heavier model for `draft`, lighter for
 * `translate` — deferred to v0.2 per spec §5.3); v0.1.0 treats both as
 * single-message completion calls.
 *
 * The `$context` parameter on `draft()` and the `$fromLocale` /
 * `$toLocale` parameters on `translate()` are honored at the
 * `PromptBuilder` layer; drivers ignore them.
 */
abstract class AbstractLlmClient implements LlmClient
{
    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model,
        protected readonly int $timeoutSeconds = 120,
    ) {}

    public function draft(string $prompt, array $context): string
    {
        return $this->request($prompt);
    }

    public function translate(string $text, string $fromLocale, string $toLocale): string
    {
        return $this->request($text);
    }

    /**
     * Send one user message to the provider and return the assistant's text.
     */
    abstract protected function request(string $message): string;

    protected function requireApiKey(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException(
                static::class.' has no API key configured. Set the matching '
                .'STRINGER_*_API_KEY environment variable.'
            );
        }
    }
}

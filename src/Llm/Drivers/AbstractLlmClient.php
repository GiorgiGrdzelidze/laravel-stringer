<?php

declare(strict_types=1);

namespace Stringer\Laravel\Llm\Drivers;

use RuntimeException;
use Stringer\Laravel\Contracts\LlmClient;

/**
 * Shared scaffolding for the four built-in LLM drivers.
 *
 * Subclasses implement `request()`, which sends one user message to the
 * provider and returns the assistant's plain-text reply. `draft()` and
 * `translate()` build the user message and delegate.
 */
abstract class AbstractLlmClient implements LlmClient
{
    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model,
    ) {}

    /**
     * @param  array<string, list<array{title: string, excerpt: string}>>  $context
     */
    public function draft(string $prompt, array $context): string
    {
        $serialized = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($serialized === false) {
            throw new RuntimeException('Could not serialize draft context for the LLM.');
        }

        $message = $prompt
            ."\n\n## Reference context — recent published posts\n\n"
            .$serialized;

        return $this->request($message);
    }

    public function translate(string $text, string $fromLocale, string $toLocale): string
    {
        $message = "Translate the following text from {$fromLocale} to {$toLocale}. "
            .'Preserve markdown structure, code blocks, and proper nouns. '
            ."Output only the translation — no preamble, no quoting.\n\n"
            .$text;

        return $this->request($message);
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

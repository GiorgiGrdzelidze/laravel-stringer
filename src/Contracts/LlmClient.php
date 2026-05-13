<?php

declare(strict_types=1);

namespace Stringer\Laravel\Contracts;

/**
 * Driver-agnostic LLM facade.
 *
 * Resolved via the container; the concrete driver is selected by
 * `config('stringer.llm.driver')` and built by `LlmManager`.
 */
interface LlmClient
{
    /**
     * Produce a draft in the primary locale.
     *
     * @param  array<string, list<array{title: string, excerpt: string}>>  $context
     */
    public function draft(string $prompt, array $context): string;

    /**
     * Translate already-drafted text from $fromLocale to $toLocale.
     */
    public function translate(string $text, string $fromLocale, string $toLocale): string;
}

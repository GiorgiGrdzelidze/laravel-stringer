<?php

declare(strict_types=1);

namespace Stringer\Laravel\Tests\Doubles;

use RuntimeException;
use Stringer\Laravel\Contracts\LlmClient;

/**
 * Hand-scripted `LlmClient` for tests. Returns one queued response per
 * `draft()` call and pops one off the `translateQueue` per `translate()`
 * call so callers can assert call counts + ordering without mocks.
 */
final class ScriptedLlmClient implements LlmClient
{
    public string $lastDraftPrompt = '';

    /** @var array<string, mixed> Last `$context` passed to `draft()`. */
    public array $lastDraftContext = [];

    /** @var list<array{from: string, to: string, text: string}> */
    public array $translateCalls = [];

    public int $draftCalls = 0;

    /**
     * @param  list<string>  $translateQueue
     */
    public function __construct(
        private readonly string $draftResponse,
        private array $translateQueue = [],
    ) {}

    public function draft(string $prompt, array $context): string
    {
        $this->draftCalls++;
        $this->lastDraftPrompt = $prompt;
        $this->lastDraftContext = $context;

        return $this->draftResponse;
    }

    public function translate(string $text, string $fromLocale, string $toLocale): string
    {
        $this->translateCalls[] = ['from' => $fromLocale, 'to' => $toLocale, 'text' => $text];

        $next = array_shift($this->translateQueue);
        if ($next === null) {
            throw new RuntimeException("ScriptedLlmClient: translateQueue exhausted (asked for {$toLocale}).");
        }

        return $next;
    }
}

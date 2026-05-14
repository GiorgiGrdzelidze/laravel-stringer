<?php

declare(strict_types=1);

namespace Stringer\Laravel\Tests\Doubles;

use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Llm\LlmManager;

/**
 * Test override of `LlmManager` that returns a scripted client and a
 * fixed model identifier — keeps DraftGenerator's `modelName()`
 * round-trip deterministic without touching real config.
 *
 * Requires `LlmManager` to be non-final (it is, since Phase 6).
 */
final class ManagerStub extends LlmManager
{
    public function __construct(
        private readonly LlmClient $client,
        private readonly string $modelName,
    ) {
        parent::__construct(app('config'));
    }

    public function make(): LlmClient
    {
        return $this->client;
    }

    public function modelName(): string
    {
        return $this->modelName;
    }
}

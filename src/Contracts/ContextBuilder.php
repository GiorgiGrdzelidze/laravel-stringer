<?php

declare(strict_types=1);

namespace Stringer\Laravel\Contracts;

/**
 * Host-implemented source of public-only content used as LLM context.
 *
 * Implementations MUST NOT expose private / admin / finance data. The
 * architectural boundary is enforced by the host adapter's constructor
 * (which should type-hint only public models).
 */
interface ContextBuilder
{
    /**
     * @return array<string, list<array{title: string, excerpt: string}>>
     */
    public function build(): array;
}

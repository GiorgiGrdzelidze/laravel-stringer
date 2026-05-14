<?php

declare(strict_types=1);

namespace Stringer\Laravel\Tests\Doubles;

use Stringer\Laravel\Contracts\ContextBuilder;

/**
 * Returns a fixed context payload for tests.
 */
final class StaticContextBuilder implements ContextBuilder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly array $context = [],
    ) {}

    public function build(): array
    {
        /** @var array<string, list<array{title: string, excerpt: string}>> */
        return $this->context;
    }
}

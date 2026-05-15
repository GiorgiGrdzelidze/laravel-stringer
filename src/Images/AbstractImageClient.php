<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images;

use RuntimeException;
use Stringer\Laravel\Contracts\ImageGenerator;

/**
 * Shared scaffolding for image drivers. Subclasses implement
 * `generate()` directly; this base just enforces an `apiKey` presence
 * check via `requireApiKey()` and exposes the configured model + HTTP
 * timeout. The stock-photo driver (`UnsplashClient`) uses the same
 * shape — `model` is the unused slot.
 */
abstract class AbstractImageClient implements ImageGenerator
{
    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model = '',
        protected readonly int $timeoutSeconds = 60,
    ) {}

    protected function requireApiKey(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException(
                static::class.' has no API key configured. Set the matching '
                .'STRINGER_*_API_KEY (or STRINGER_*_ACCESS_KEY) environment variable.'
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Stringer\Laravel\Contracts;

use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;

/**
 * Driver-agnostic image facade.
 *
 * Resolved via the container; the concrete driver is selected by
 * `config('stringer.images.driver')` and built by `ImageManager`.
 *
 * Implementations may "generate" (AI models) or "find" (stock libraries
 * like Unsplash) — both shapes return the same `GeneratedImage` value
 * object. Hosts treat the result identically; the only difference is the
 * `sourceDriver` field on `GeneratedImage` and an optional `attribution`
 * line that hosts must render when present.
 */
interface ImageGenerator
{
    public function generate(string $prompt, ImageGenerationOptions $options): GeneratedImage;
}

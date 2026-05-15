<?php

declare(strict_types=1);

namespace Stringer\Laravel\DataObjects;

/**
 * Immutable result of one image generation / lookup call.
 *
 * `bytes` is the raw image payload — hosts hand it to
 * `addMediaFromString()` (Spatie Media Library) or any equivalent
 * binary-sink API. `extension()` returns a sane filename suffix derived
 * from `mime` so callers can name the uploaded file consistently.
 *
 * `sourceDriver` identifies the producing driver (`'imagen'`, `'unsplash'`,
 * `'dalle'`, `'flux'`, …) so hosts can render driver-specific attribution
 * or filter analytics. `attribution` is non-null for stock-photo drivers
 * that require credit (Unsplash) and null for AI generators.
 *
 * `prompt` is the final string sent to the model — recorded on media
 * `custom_properties` so an operator can later see why this cover was
 * chosen without re-running the pipeline.
 */
final readonly class GeneratedImage
{
    public function __construct(
        public string $bytes,
        public string $mime,
        public int $width,
        public int $height,
        public string $sourceDriver,
        public ?string $attribution = null,
        public ?string $prompt = null,
    ) {}

    public function extension(): string
    {
        return match ($this->mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };
    }
}

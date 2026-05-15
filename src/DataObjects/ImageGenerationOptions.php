<?php

declare(strict_types=1);

namespace Stringer\Laravel\DataObjects;

/**
 * Caller-side knobs for one image generation request.
 *
 * `size` is a `WIDTHxHEIGHT` string (e.g. `'1792x1024'`); drivers map it
 * to the closest supported model resolution. `style` is a free-form
 * sentence appended to the prompt by the driver. `seed` is optional and
 * only honored by drivers that expose deterministic sampling (FLUX). The
 * Unsplash driver ignores `style` and `seed` and uses the prompt as a
 * search query.
 */
final readonly class ImageGenerationOptions
{
    public function __construct(
        public string $size = '1792x1024',
        public ?string $style = null,
        public ?int $seed = null,
    ) {}

    /**
     * @return array{0: int, 1: int}
     */
    public function dimensions(): array
    {
        [$w, $h] = array_pad(explode('x', $this->size), 2, '0');

        return [(int) $w, (int) $h];
    }
}

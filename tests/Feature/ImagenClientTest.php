<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\Drivers\ImagenClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts the prompt to the Imagen predict endpoint and decodes the base64 body', function () {
    $pngBytes = "\x89PNG\r\n\x1A\n".str_repeat('A', 100);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'predictions' => [
                ['bytesBase64Encoded' => base64_encode($pngBytes)],
            ],
        ]),
    ]);

    $client = new ImagenClient('test-key', 'imagen-3.0-generate-001');
    $image = $client->generate(
        'a turtle on a misty mountain lake',
        new ImageGenerationOptions(size: '1792x1024', style: 'editorial illustration, muted palette'),
    );

    expect($image->bytes)->toBe($pngBytes)
        ->and($image->mime)->toBe('image/png')
        ->and($image->width)->toBe(1792)
        ->and($image->height)->toBe(1024)
        ->and($image->sourceDriver)->toBe('imagen')
        ->and($image->attribution)->toBeNull()
        ->and($image->prompt)->toContain('a turtle on a misty mountain lake')
        ->and($image->prompt)->toContain('Style: editorial illustration');

    Http::assertSent(function (Request $r): bool {
        return $r->method() === 'POST'
            && str_contains($r->url(), 'imagen-3.0-generate-001:predict')
            && str_contains($r->url(), 'key=test-key')
            && ($r['parameters']['aspectRatio'] ?? null) === '16:9'
            && ($r['parameters']['sampleCount'] ?? null) === 1
            && str_contains((string) ($r['instances'][0]['prompt'] ?? ''), 'Style: editorial illustration');
    });
});

it('maps caller size to the closest supported aspect ratio', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'predictions' => [['bytesBase64Encoded' => base64_encode('x')]],
        ]),
    ]);

    $client = new ImagenClient('test-key', 'imagen-3.0-generate-001');

    foreach (
        [
            ['1024x1024', '1:1'],
            ['1792x1024', '16:9'],
            ['1024x1792', '9:16'],
            ['1024x768', '4:3'],
            ['768x1024', '3:4'],
        ] as [$size, $expectedAspect]
    ) {
        Http::clearResolvedInstances();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [['bytesBase64Encoded' => base64_encode('x')]],
            ]),
        ]);

        $client->generate('test prompt', new ImageGenerationOptions(size: $size));

        Http::assertSent(fn (Request $r): bool => ($r['parameters']['aspectRatio'] ?? null) === $expectedAspect);
    }
});

it('throws when Imagen returns an empty payload', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['predictions' => []]),
    ]);

    $client = new ImagenClient('test-key', 'imagen-3.0-generate-001');

    expect(fn () => $client->generate('x', new ImageGenerationOptions))
        ->toThrow(RuntimeException::class, 'Imagen returned an empty');
});

it('throws when the API key is missing', function () {
    $client = new ImagenClient('', 'imagen-3.0-generate-001');

    expect(fn () => $client->generate('x', new ImageGenerationOptions))
        ->toThrow(RuntimeException::class, 'has no API key');
});

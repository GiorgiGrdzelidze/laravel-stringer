<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\Drivers\FluxClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts to fal.run with a multiple-of-64 size, then downloads the returned image url', function () {
    $pngBytes = "\x89PNG\r\n\x1A\n".str_repeat('D', 100);

    Http::fake([
        'fal.run/*' => Http::response([
            'images' => [['url' => 'https://fal.media/files/abc/cover.png']],
        ]),
        'fal.media/files/*' => Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
    ]);

    $client = new FluxClient('fal-test-key', 'fal-ai/flux/dev');
    $image = $client->generate(
        'a turtle lake',
        new ImageGenerationOptions(size: '1792x1024', style: 'editorial illustration', seed: 42),
    );

    expect($image->bytes)->toBe($pngBytes)
        ->and($image->mime)->toBe('image/png')
        ->and($image->width)->toBe(1792)
        ->and($image->height)->toBe(1024)
        ->and($image->sourceDriver)->toBe('flux');

    Http::assertSent(function (Request $r): bool {
        if (! str_contains($r->url(), 'fal.run/fal-ai/flux/dev')) {
            return false;
        }
        if ($r->method() !== 'POST') {
            return false;
        }

        return ($r['image_size']['width'] ?? null) === 1792
            && ($r['image_size']['height'] ?? null) === 1024
            && ($r['seed'] ?? null) === 42
            && str_starts_with((string) $r->header('Authorization')[0], 'Key fal-test-key');
    });
});

it('rounds odd dimensions down to the nearest multiple of 64', function () {
    Http::fake([
        'fal.run/*' => Http::response(['images' => [['url' => 'https://fal.media/x.png']]]),
        'fal.media/*' => Http::response('x', 200, ['Content-Type' => 'image/png']),
    ]);

    $client = new FluxClient('fal-test-key', 'fal-ai/flux/dev');
    $client->generate('x', new ImageGenerationOptions(size: '1023x767'));

    Http::assertSent(function (Request $r): bool {
        if (! str_contains($r->url(), 'fal.run/')) {
            return false;
        }

        return ($r['image_size']['width'] ?? null) === 960
            && ($r['image_size']['height'] ?? null) === 704;
    });
});

it('throws when the API key is missing', function () {
    $client = new FluxClient('', 'fal-ai/flux/dev');

    expect(fn () => $client->generate('x', new ImageGenerationOptions))
        ->toThrow(RuntimeException::class);
});

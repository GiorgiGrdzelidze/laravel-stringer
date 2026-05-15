<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\Drivers\DallE3Client;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts to the OpenAI image-generation endpoint and decodes b64 bytes', function () {
    $pngBytes = "\x89PNG\r\n\x1A\n".str_repeat('C', 100);

    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [['b64_json' => base64_encode($pngBytes)]],
        ]),
    ]);

    $client = new DallE3Client('sk-test', 'dall-e-3');
    $image = $client->generate(
        'a turtle lake',
        new ImageGenerationOptions(size: '1792x1024', style: 'editorial illustration'),
    );

    expect($image->bytes)->toBe($pngBytes)
        ->and($image->mime)->toBe('image/png')
        ->and($image->width)->toBe(1792)
        ->and($image->height)->toBe(1024)
        ->and($image->sourceDriver)->toBe('dalle')
        ->and($image->prompt)->toContain('Style: editorial illustration');

    Http::assertSent(function (Request $r): bool {
        return $r->method() === 'POST'
            && str_contains($r->url(), 'api.openai.com/v1/images/generations')
            && ($r['model'] ?? null) === 'dall-e-3'
            && ($r['size'] ?? null) === '1792x1024'
            && ($r['response_format'] ?? null) === 'b64_json'
            && str_contains((string) $r->header('Authorization')[0], 'Bearer sk-test');
    });
});

it('snaps a square size to 1024x1024', function () {
    Http::fake([
        'api.openai.com/v1/images/generations' => Http::response([
            'data' => [['b64_json' => base64_encode('x')]],
        ]),
    ]);

    $client = new DallE3Client('sk-test', 'dall-e-3');
    $client->generate('x', new ImageGenerationOptions(size: '1024x1024'));

    Http::assertSent(fn (Request $r): bool => ($r['size'] ?? null) === '1024x1024');
});

it('throws when the API key is missing', function () {
    $client = new DallE3Client('', 'dall-e-3');

    expect(fn () => $client->generate('x', new ImageGenerationOptions))
        ->toThrow(RuntimeException::class);
});

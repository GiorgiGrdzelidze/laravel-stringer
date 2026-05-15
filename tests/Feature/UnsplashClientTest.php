<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\Drivers\UnsplashClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('searches the photo library, downloads the top match, and pings the download_location', function () {
    $jpgBytes = "\xFF\xD8\xFF\xE0".str_repeat('B', 100);

    Http::fake([
        'api.unsplash.com/search/photos*' => Http::response([
            'results' => [
                [
                    'urls' => ['raw' => 'https://images.unsplash.com/photo-123'],
                    'links' => ['download_location' => 'https://api.unsplash.com/photos/abc/download'],
                    'user' => ['name' => 'Jane Doe', 'links' => ['html' => 'https://unsplash.com/@janedoe']],
                ],
            ],
        ]),
        'images.unsplash.com/*' => Http::response($jpgBytes, 200, ['Content-Type' => 'image/jpeg']),
        'api.unsplash.com/photos/*/download' => Http::response(['url' => 'ignored']),
    ]);

    $client = new UnsplashClient('test-access-key');
    $image = $client->generate('turtle lake reflective water', new ImageGenerationOptions(size: '1600x900'));

    expect($image->bytes)->toBe($jpgBytes)
        ->and($image->mime)->toBe('image/jpeg')
        ->and($image->width)->toBe(1600)
        ->and($image->height)->toBe(900)
        ->and($image->sourceDriver)->toBe('unsplash')
        ->and($image->attribution)->toContain('Photo by Jane Doe on Unsplash');

    Http::assertSent(function (Request $r): bool {
        return $r->method() === 'GET'
            && str_contains($r->url(), 'api.unsplash.com/search/photos')
            && str_contains($r->url(), 'orientation=landscape')
            && str_starts_with((string) $r->header('Authorization')[0], 'Client-ID test-access-key');
    });

    Http::assertSent(function (Request $r): bool {
        return str_contains($r->url(), 'images.unsplash.com/photo-123')
            && str_contains($r->url(), 'w=1600')
            && str_contains($r->url(), 'h=900')
            && str_contains($r->url(), 'fit=crop');
    });

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/photos/abc/download'));
});

it('compacts a long-form image prompt into a short search query', function () {
    Http::fake([
        'api.unsplash.com/search/photos*' => Http::response([
            'results' => [[
                'urls' => ['raw' => 'https://images.unsplash.com/x'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/x/download'],
                'user' => ['name' => 'X', 'links' => ['html' => 'https://unsplash.com/@x']],
            ]],
        ]),
        'images.unsplash.com/*' => Http::response('ok', 200, ['Content-Type' => 'image/jpeg']),
        'api.unsplash.com/photos/*/download' => Http::response('', 200),
    ]);

    $client = new UnsplashClient('test-access-key');
    $client->generate(
        'turtle reflections on a still lake at dawn. Style: editorial, muted palette, no text',
        new ImageGenerationOptions,
    );

    Http::assertSent(function (Request $r): bool {
        if (! str_contains($r->url(), 'api.unsplash.com/search/photos')) {
            return false;
        }

        // The first sentence ("turtle reflections on a still lake at dawn") becomes the query,
        // not the style fragment.
        return str_contains($r->url(), 'query=turtle')
            && ! str_contains($r->url(), 'muted+palette');
    });
});

it('throws when Unsplash returns no matches', function () {
    Http::fake([
        'api.unsplash.com/search/photos*' => Http::response(['results' => []]),
    ]);

    $client = new UnsplashClient('test-access-key');

    expect(fn () => $client->generate('something obscure', new ImageGenerationOptions))
        ->toThrow(RuntimeException::class, 'Unsplash returned no matches');
});

it('throws when the access key is missing', function () {
    $client = new UnsplashClient('');

    expect(fn () => $client->generate('x', new ImageGenerationOptions))
        ->toThrow(RuntimeException::class, 'has no API key');
});

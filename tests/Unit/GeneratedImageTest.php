<?php

declare(strict_types=1);

use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;

it('maps known mime types to file extensions', function () {
    expect((new GeneratedImage('x', 'image/png', 1, 1, 'imagen'))->extension())->toBe('png')
        ->and((new GeneratedImage('x', 'image/jpeg', 1, 1, 'unsplash'))->extension())->toBe('jpg')
        ->and((new GeneratedImage('x', 'image/jpg', 1, 1, 'unsplash'))->extension())->toBe('jpg')
        ->and((new GeneratedImage('x', 'image/webp', 1, 1, 'imagen'))->extension())->toBe('webp')
        ->and((new GeneratedImage('x', 'image/gif', 1, 1, 'imagen'))->extension())->toBe('gif')
        ->and((new GeneratedImage('x', 'application/octet-stream', 1, 1, 'imagen'))->extension())->toBe('bin');
});

it('parses size strings into dimension pairs', function () {
    expect((new ImageGenerationOptions(size: '1792x1024'))->dimensions())->toBe([1792, 1024])
        ->and((new ImageGenerationOptions(size: '1024x1024'))->dimensions())->toBe([1024, 1024])
        ->and((new ImageGenerationOptions(size: 'malformed'))->dimensions())->toBe([0, 0]);
});

it('defaults are sensible for both DTOs', function () {
    $options = new ImageGenerationOptions;
    expect($options->size)->toBe('1792x1024')
        ->and($options->style)->toBeNull()
        ->and($options->seed)->toBeNull();

    $image = new GeneratedImage('x', 'image/png', 1, 1, 'imagen');
    expect($image->attribution)->toBeNull()
        ->and($image->prompt)->toBeNull();
});

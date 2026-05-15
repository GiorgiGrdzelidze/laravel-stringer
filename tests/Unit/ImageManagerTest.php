<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Stringer\Laravel\Images\Drivers\DallE3Client;
use Stringer\Laravel\Images\Drivers\FluxClient;
use Stringer\Laravel\Images\Drivers\ImagenClient;
use Stringer\Laravel\Images\Drivers\UnsplashClient;
use Stringer\Laravel\Images\ImageManager;

$imageConfig = function (string $driver): Repository {
    return new Repository([
        'stringer' => [
            'images' => [
                'driver' => $driver,
                'http_timeout' => 45,
                'drivers' => [
                    'imagen' => ['api_key' => 'gemini-key', 'model' => 'imagen-3.0-generate-001'],
                    'unsplash' => ['access_key' => 'unsplash-key', 'model' => ''],
                    'dalle' => ['api_key' => 'openai-key', 'model' => 'dall-e-3'],
                    'flux' => ['api_key' => 'fal-key', 'model' => 'fal-ai/flux/dev'],
                ],
            ],
        ],
    ]);
};

it('resolves the imagen driver', function () use ($imageConfig) {
    expect((new ImageManager($imageConfig('imagen')))->make())->toBeInstanceOf(ImagenClient::class);
});

it('resolves the unsplash driver', function () use ($imageConfig) {
    expect((new ImageManager($imageConfig('unsplash')))->make())->toBeInstanceOf(UnsplashClient::class);
});

it('resolves the dalle driver', function () use ($imageConfig) {
    expect((new ImageManager($imageConfig('dalle')))->make())->toBeInstanceOf(DallE3Client::class);
});

it('resolves the flux driver', function () use ($imageConfig) {
    expect((new ImageManager($imageConfig('flux')))->make())->toBeInstanceOf(FluxClient::class);
});

it('throws on an unknown driver name', function () use ($imageConfig) {
    expect(fn () => (new ImageManager($imageConfig('mystery')))->make())
        ->toThrow(InvalidArgumentException::class, "Unknown image driver 'mystery'");
});

it('re-reads config on every make() so runtime swaps take effect', function () use ($imageConfig) {
    $config = $imageConfig('imagen');
    $manager = new ImageManager($config);

    expect($manager->make())->toBeInstanceOf(ImagenClient::class);

    $config->set('stringer.images.driver', 'unsplash');

    expect($manager->make())->toBeInstanceOf(UnsplashClient::class);
});

it('with() resolves a non-default driver without mutating config', function () use ($imageConfig) {
    $manager = new ImageManager($imageConfig('imagen'));

    expect($manager->with('unsplash'))->toBeInstanceOf(UnsplashClient::class)
        ->and($manager->make())->toBeInstanceOf(ImagenClient::class);
});

it('defaults to imagen when config is missing', function () {
    $manager = new ImageManager(new Repository(['stringer' => ['images' => []]]));

    expect($manager->defaultDriver())->toBe('imagen');
});

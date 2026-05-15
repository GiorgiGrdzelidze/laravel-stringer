<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Stringer\Laravel\Contracts\ImageGenerator;
use Stringer\Laravel\Images\Drivers\DallE3Client;
use Stringer\Laravel\Images\Drivers\FluxClient;
use Stringer\Laravel\Images\Drivers\GeminiImageClient;
use Stringer\Laravel\Images\Drivers\ImagenClient;
use Stringer\Laravel\Images\Drivers\PicsumClient;
use Stringer\Laravel\Images\Drivers\UnsplashClient;

/**
 * Resolves the configured image driver into an `ImageGenerator`.
 *
 * Driver name comes from `config('stringer.images.driver')`; per-driver
 * config lives under `config('stringer.images.drivers.<name>')`. Config
 * is read inside `make()` (not snapshotted at construction) so runtime
 * `config()->set(...)` swaps take effect on the next resolve — useful
 * for tests and for in-bot driver switches.
 *
 * `with(string $driver)` returns an ad-hoc client for a non-default
 * driver — used by the Telegram "Try Unsplash" confirm-flow button to
 * regenerate via a different backend without mutating global config.
 */
class ImageManager
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function make(): ImageGenerator
    {
        return $this->with($this->defaultDriver());
    }

    public function with(string $driver): ImageGenerator
    {
        $apiKey = (string) $this->config->get("stringer.images.drivers.{$driver}.api_key", '');
        if ($apiKey === '') {
            $apiKey = (string) $this->config->get("stringer.images.drivers.{$driver}.access_key", '');
        }
        $model = (string) $this->config->get("stringer.images.drivers.{$driver}.model", '');
        $timeout = (int) $this->config->get('stringer.images.http_timeout', 60);

        return match ($driver) {
            'imagen' => new ImagenClient($apiKey, $model, $timeout),
            'gemini-image' => new GeminiImageClient($apiKey, $model, $timeout),
            'unsplash' => new UnsplashClient($apiKey, $model, $timeout),
            'dalle' => new DallE3Client($apiKey, $model, $timeout),
            'flux' => new FluxClient($apiKey, $model, $timeout),
            'picsum' => new PicsumClient($apiKey, $model, $timeout),
            default => throw new InvalidArgumentException(
                "Unknown image driver '{$driver}'. Supported: imagen, gemini-image, unsplash, dalle, flux, picsum."
            ),
        };
    }

    public function defaultDriver(): string
    {
        return (string) $this->config->get('stringer.images.driver', 'imagen');
    }
}

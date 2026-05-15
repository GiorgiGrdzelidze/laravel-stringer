<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\AbstractImageClient;

/**
 * Placeholder driver — fetches a random image from picsum.photos at the
 * requested size. Requires no API key and no account.
 *
 * Intended for development / smoke-testing the end-to-end cover pipeline
 * before adopters wire up a real generator. The image is unrelated to
 * the prompt (picsum returns a random photo from its rotating pool), so
 * production use only makes sense if you want decorative stock images
 * with zero curation.
 *
 * `seed` is honored — picsum supports `?random={seed}` to keep the image
 * stable per topic.
 */
final class PicsumClient extends AbstractImageClient
{
    private const ENDPOINT = 'https://picsum.photos/%d/%d';

    public function generate(string $prompt, ImageGenerationOptions $options): GeneratedImage
    {
        [$width, $height] = $options->dimensions();
        if ($width <= 0 || $height <= 0) {
            $width = 1792;
            $height = 1024;
        }

        $url = sprintf(self::ENDPOINT, $width, $height);
        if ($options->seed !== null) {
            $url .= '?random='.$options->seed;
        }

        $response = Http::timeout($this->timeoutSeconds)->get($url)->throw();

        $contentType = strtolower($response->header('Content-Type') ?: 'image/jpeg');
        if (str_contains($contentType, ';')) {
            $contentType = trim((string) strtok($contentType, ';'));
        }

        $bytes = $response->body();
        if ($bytes === '') {
            throw new RuntimeException('picsum.photos returned an empty body.');
        }

        return new GeneratedImage(
            bytes: $bytes,
            mime: $contentType === '' ? 'image/jpeg' : $contentType,
            width: $width,
            height: $height,
            sourceDriver: 'picsum',
            attribution: 'Random placeholder via picsum.photos (Unsplash, no curation)',
            prompt: $prompt,
        );
    }

    /**
     * Picsum needs no auth — override the base's check to a no-op so
     * `ImageManager` doesn't fail when the (unused) api_key slot is blank.
     */
    protected function requireApiKey(): void
    {
        // intentional no-op
    }
}

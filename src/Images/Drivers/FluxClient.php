<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\AbstractImageClient;

/**
 * Black Forest Labs FLUX driver via fal.ai. Sync endpoint
 * (`fal-ai/flux/dev`) — returns a hosted PNG URL we download.
 *
 * Default model: `fal-ai/flux/dev`. Auth: `STRINGER_FAL_KEY`. FLUX accepts
 * arbitrary width/height (multiples of 64); we round the caller's size
 * down to the nearest multiple of 64 in both axes.
 */
final class FluxClient extends AbstractImageClient
{
    private const ENDPOINT_TEMPLATE = 'https://fal.run/%s';

    public function generate(string $prompt, ImageGenerationOptions $options): GeneratedImage
    {
        $this->requireApiKey();

        $styledPrompt = $options->style !== null && $options->style !== ''
            ? trim($prompt).'. Style: '.trim($options->style)
            : $prompt;

        [$width, $height] = $options->dimensions();
        $width = (int) (floor($width / 64) * 64);
        $height = (int) (floor($height / 64) * 64);
        if ($width <= 0) {
            $width = 1024;
        }
        if ($height <= 0) {
            $height = 1024;
        }

        $payload = [
            'prompt' => $styledPrompt,
            'image_size' => ['width' => $width, 'height' => $height],
            'num_images' => 1,
            'enable_safety_checker' => true,
        ];
        if ($options->seed !== null) {
            $payload['seed'] = $options->seed;
        }

        $endpoint = sprintf(self::ENDPOINT_TEMPLATE, $this->model !== '' ? $this->model : 'fal-ai/flux/dev');

        $response = Http::withHeaders(['Authorization' => 'Key '.$this->apiKey])
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->post($endpoint, $payload)
            ->throw();

        $imageUrl = (string) data_get($response->json(), 'images.0.url', '');
        if ($imageUrl === '') {
            throw new RuntimeException('FLUX returned an empty or malformed response.');
        }

        $imageResponse = Http::timeout($this->timeoutSeconds)->get($imageUrl)->throw();

        $contentType = strtolower($imageResponse->header('Content-Type') ?: 'image/png');
        if (str_contains($contentType, ';')) {
            $contentType = trim((string) strtok($contentType, ';'));
        }

        return new GeneratedImage(
            bytes: $imageResponse->body(),
            mime: $contentType === '' ? 'image/png' : $contentType,
            width: $width,
            height: $height,
            sourceDriver: 'flux',
            attribution: null,
            prompt: $styledPrompt,
        );
    }
}

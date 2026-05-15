<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\AbstractImageClient;

/**
 * Google Imagen 3 driver against `generativelanguage.googleapis.com`.
 *
 * Default model: `imagen-3.0-generate-001` (overridable via
 * `STRINGER_IMAGEN_MODEL`). Auth: same API key as the Gemini text
 * driver — set `STRINGER_GEMINI_API_KEY` once and both flows work.
 *
 * Imagen supports a fixed set of aspects: 1:1, 9:16, 16:9, 3:4, 4:3.
 * We map the caller's `size` to the closest aspect string and let
 * Imagen pick the resolution. The returned `width`/`height` reflect
 * the asked-for dimensions so Spatie conversions can be derived
 * predictably downstream.
 */
final class ImagenClient extends AbstractImageClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:predict';

    public function generate(string $prompt, ImageGenerationOptions $options): GeneratedImage
    {
        $this->requireApiKey();

        $styledPrompt = $options->style !== null && $options->style !== ''
            ? trim($prompt).'. Style: '.trim($options->style)
            : $prompt;

        [$width, $height] = $options->dimensions();
        $aspect = $this->aspectRatio($width, $height);

        $response = Http::asJson()
            ->timeout($this->timeoutSeconds)
            ->withQueryParameters(['key' => $this->apiKey])
            ->post(sprintf(self::ENDPOINT, $this->model), [
                'instances' => [[
                    'prompt' => $styledPrompt,
                ]],
                'parameters' => [
                    'sampleCount' => 1,
                    'aspectRatio' => $aspect,
                ],
            ])
            ->throw();

        $base64 = data_get($response->json(), 'predictions.0.bytesBase64Encoded');
        if (! is_string($base64) || $base64 === '') {
            throw new RuntimeException('Imagen returned an empty or malformed response.');
        }

        $bytes = base64_decode($base64, true);
        if ($bytes === false) {
            throw new RuntimeException('Imagen returned non-base64 image bytes.');
        }

        return new GeneratedImage(
            bytes: $bytes,
            mime: 'image/png',
            width: $width,
            height: $height,
            sourceDriver: 'imagen',
            attribution: null,
            prompt: $styledPrompt,
        );
    }

    private function aspectRatio(int $width, int $height): string
    {
        if ($height === 0) {
            return '1:1';
        }
        $ratio = $width / $height;

        return match (true) {
            $ratio >= 1.7 => '16:9',
            $ratio >= 1.25 => '4:3',
            $ratio >= 0.85 => '1:1',
            $ratio >= 0.65 => '3:4',
            default => '9:16',
        };
    }
}

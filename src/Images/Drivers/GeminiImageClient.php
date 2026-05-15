<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\AbstractImageClient;

/**
 * Gemini-native image-generation driver.
 *
 * Talks to `gemini-2.5-flash-image` (or other `gemini-*-image-*` models)
 * via the regular `generateContent` endpoint — *not* the Imagen
 * `predict` endpoint that requires a paid Google AI plan. This is the
 * free-tier path for hosts that already have a Gemini text key.
 *
 * The response shape is `candidates[0].content.parts[*].inlineData` —
 * one part per generated image, with `mimeType` and base64 `data`.
 * We take the first image part.
 *
 * Aspect ratio is requested via `imageConfig.aspectRatio` and we mirror
 * Imagen's snap-to-supported-aspect heuristic.
 */
final class GeminiImageClient extends AbstractImageClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

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
                'contents' => [[
                    'parts' => [['text' => $styledPrompt]],
                    'role' => 'user',
                ]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => $aspect,
                    ],
                ],
            ])
            ->throw();

        $parts = (array) data_get($response->json(), 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            $mime = (string) data_get($part, 'inlineData.mimeType', '');
            $base64 = (string) data_get($part, 'inlineData.data', '');

            if ($mime !== '' && $base64 !== '' && str_starts_with($mime, 'image/')) {
                $bytes = base64_decode($base64, true);
                if ($bytes === false) {
                    throw new RuntimeException('Gemini image driver returned non-base64 bytes.');
                }

                return new GeneratedImage(
                    bytes: $bytes,
                    mime: $mime,
                    width: $width,
                    height: $height,
                    sourceDriver: 'gemini-image',
                    attribution: null,
                    prompt: $styledPrompt,
                );
            }
        }

        throw new RuntimeException('Gemini image driver returned no image parts.');
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

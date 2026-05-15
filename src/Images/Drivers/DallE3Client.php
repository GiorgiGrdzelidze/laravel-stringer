<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\AbstractImageClient;

/**
 * OpenAI DALL-E 3 driver.
 *
 * Default model: `dall-e-3` (overridable via `STRINGER_DALLE_MODEL`).
 * Auth: `STRINGER_OPENAI_API_KEY`. DALL-E 3 accepts only three sizes:
 * 1024x1024, 1792x1024, 1024x1792. We snap the caller's size to the
 * closest of those.
 */
final class DallE3Client extends AbstractImageClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/images/generations';

    public function generate(string $prompt, ImageGenerationOptions $options): GeneratedImage
    {
        $this->requireApiKey();

        $styledPrompt = $options->style !== null && $options->style !== ''
            ? trim($prompt).'. Style: '.trim($options->style)
            : $prompt;

        [$width, $height] = $options->dimensions();
        $size = $this->snapSize($width, $height);

        $response = Http::withToken($this->apiKey)
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->post(self::ENDPOINT, [
                'model' => $this->model !== '' ? $this->model : 'dall-e-3',
                'prompt' => $styledPrompt,
                'n' => 1,
                'size' => $size,
                'response_format' => 'b64_json',
                'quality' => 'standard',
            ])
            ->throw();

        $base64 = data_get($response->json(), 'data.0.b64_json');
        if (! is_string($base64) || $base64 === '') {
            throw new RuntimeException('DALL-E returned an empty or malformed response.');
        }

        $bytes = base64_decode($base64, true);
        if ($bytes === false) {
            throw new RuntimeException('DALL-E returned non-base64 image bytes.');
        }

        [$snappedW, $snappedH] = explode('x', $size);

        return new GeneratedImage(
            bytes: $bytes,
            mime: 'image/png',
            width: (int) $snappedW,
            height: (int) $snappedH,
            sourceDriver: 'dalle',
            attribution: null,
            prompt: $styledPrompt,
        );
    }

    private function snapSize(int $width, int $height): string
    {
        if ($height === 0) {
            return '1024x1024';
        }
        $ratio = $width / $height;

        return match (true) {
            $ratio >= 1.5 => '1792x1024',
            $ratio <= 0.75 => '1024x1792',
            default => '1024x1024',
        };
    }
}

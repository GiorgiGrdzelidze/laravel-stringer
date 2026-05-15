<?php

declare(strict_types=1);

namespace Stringer\Laravel\Images\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stringer\Laravel\DataObjects\GeneratedImage;
use Stringer\Laravel\DataObjects\ImageGenerationOptions;
use Stringer\Laravel\Images\AbstractImageClient;

/**
 * Unsplash driver — searches the photo library, downloads the top
 * landscape match, and returns it through the same `ImageGenerator`
 * contract. Not "generation"; we call it a driver because hosts treat
 * the result identically to an AI render.
 *
 * Auth: `STRINGER_UNSPLASH_ACCESS_KEY`. Unsplash requires user-facing
 * attribution; we fill `GeneratedImage::$attribution` with a
 * `"Photo by {name} on Unsplash"` line and rely on the host to surface
 * it near the image. We also POST to the photo's `links.download_location`
 * after picking it, per Unsplash API guidelines.
 *
 * Style and seed are ignored — they only make sense for generative
 * drivers.
 */
final class UnsplashClient extends AbstractImageClient
{
    private const SEARCH_ENDPOINT = 'https://api.unsplash.com/search/photos';

    public function generate(string $prompt, ImageGenerationOptions $options): GeneratedImage
    {
        $this->requireApiKey();

        $query = $this->compactQuery($prompt);
        [$width, $height] = $options->dimensions();
        $orientation = $width >= $height ? 'landscape' : 'portrait';

        $search = Http::withHeaders([
            'Authorization' => 'Client-ID '.$this->apiKey,
            'Accept-Version' => 'v1',
        ])
            ->timeout($this->timeoutSeconds)
            ->get(self::SEARCH_ENDPOINT, [
                'query' => $query,
                'per_page' => 10,
                'orientation' => $orientation,
                'content_filter' => 'high',
            ])
            ->throw();

        /** @var array<int, array<string, mixed>> $results */
        $results = (array) data_get($search->json(), 'results', []);
        if ($results === []) {
            throw new RuntimeException("Unsplash returned no matches for query '{$query}'.");
        }

        $picked = $results[0];
        $rawUrl = (string) data_get($picked, 'urls.raw', '');
        if ($rawUrl === '') {
            throw new RuntimeException('Unsplash result is missing a downloadable URL.');
        }

        $downloadUrl = $rawUrl.'&w='.$width.'&h='.$height.'&fit=crop&fm=jpg&q=85';
        $imageResponse = Http::timeout($this->timeoutSeconds)->get($downloadUrl)->throw();

        $trigger = (string) data_get($picked, 'links.download_location', '');
        if ($trigger !== '') {
            Http::withHeaders(['Authorization' => 'Client-ID '.$this->apiKey])
                ->timeout($this->timeoutSeconds)
                ->get($trigger);
        }

        $photographerName = (string) data_get($picked, 'user.name', 'Unsplash');
        $photographerLink = (string) data_get($picked, 'user.links.html', 'https://unsplash.com');

        return new GeneratedImage(
            bytes: $imageResponse->body(),
            mime: 'image/jpeg',
            width: $width,
            height: $height,
            sourceDriver: 'unsplash',
            attribution: sprintf('Photo by %s on Unsplash (%s)', $photographerName, $photographerLink),
            prompt: $query,
        );
    }

    /**
     * Compact a long-form image prompt into 3-6 search keywords. Strips
     * style directives ("editorial illustration, muted palette…") that
     * Unsplash would dislike, then keeps the first content-bearing
     * fragment up to ~60 characters.
     */
    private function compactQuery(string $prompt): string
    {
        $first = (string) strtok(trim($prompt), '.,;');
        $first = preg_replace('/\s+/', ' ', $first) ?? $first;

        return mb_substr($first, 0, 60);
    }
}

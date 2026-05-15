<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Images\ImageManager;
use Stringer\Laravel\Jobs\GenerateCoverImageJob;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Prompts\DbPromptBuilder;
use Stringer\Laravel\Prompts\DefaultPromptBuilder;
use Stringer\Laravel\Tests\Doubles\CapturingContentTarget;
use Stringer\Laravel\Tests\Doubles\ScriptedLlmClient;
use Stringer\Laravel\Tests\Doubles\StubArticle;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('stringer.images.enabled', true);
    config()->set('stringer.images.driver', 'imagen');
    config()->set('stringer.images.master_size', '1792x1024');
    config()->set('stringer.images.max_regenerates_per_topic', 3);
    config()->set('stringer.images.style', 'editorial illustration, muted palette');
    config()->set('stringer.images.drivers.imagen.api_key', 'test-gemini-key');
    config()->set('stringer.images.drivers.imagen.model', 'imagen-3.0-generate-001');
    config()->set('stringer.images.drivers.unsplash.access_key', 'test-unsplash-key');
    config()->set('stringer.locales.primary', 'en');
    config()->set('stringer.models.article', StubArticle::class);

    app()->bind(PromptBuilder::class, fn () => new DbPromptBuilder(
        new DefaultPromptBuilder(app('config')),
    ));

    // Cover job polishes the meta-instruction prompt through the LLM into
    // a literal visual prompt before sending it to the image driver. Stub
    // the LLM with a fixed visual-prompt string so the image driver gets
    // something predictable.
    app()->bind(LlmClient::class, fn () => new ScriptedLlmClient(
        'A documentary photograph of a misty Tbilisi reservoir at dawn, single wooden rowboat in the lower third, soft cool light, no figures visible, depth of field. Style: editorial illustration, muted palette.',
        [],
    ));

    StubArticle::ensureSchema();

    Http::preventStrayRequests();
});

function makeDraftedTopicWithArticle(): BlogTopic
{
    $article = StubArticle::create([
        'title' => 'Turtle Lake at dawn',
        'excerpt' => "A walk around Tbilisi's reservoir.",
    ]);

    return BlogTopic::create([
        'hint' => 'turtle lake',
        'status' => TopicStatus::Drafted,
        'source' => TopicSource::Manual,
        'article_id' => $article->id,
        'requested_by_chat_id' => 12345,
    ]);
}

it('returns silently when image generation is globally disabled', function () {
    config()->set('stringer.images.enabled', false);

    $target = new CapturingContentTarget;
    app()->instance(ContentTarget::class, $target);

    $topic = makeDraftedTopicWithArticle();

    (new GenerateCoverImageJob($topic->id))->handle(
        app(ImageManager::class),
        app(PromptBuilder::class),
        $target,
        app('config'),
        app(LlmClient::class),
    );

    expect($target->coversCaptured)->toBeEmpty();
});

it('returns silently when the topic has no article attached', function () {
    $target = new CapturingContentTarget;

    $topic = BlogTopic::create([
        'hint' => 'no article',
        'status' => TopicStatus::Drafted,
        'source' => TopicSource::Manual,
    ]);

    (new GenerateCoverImageJob($topic->id))->handle(
        app(ImageManager::class),
        app(PromptBuilder::class),
        $target,
        app('config'),
        app(LlmClient::class),
    );

    expect($target->coversCaptured)->toBeEmpty();
});

it('builds the visual prompt, calls Imagen, and hands bytes to attachCover', function () {
    $pngBytes = "\x89PNG\r\n\x1A\n".str_repeat('Z', 200);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'predictions' => [['bytesBase64Encoded' => base64_encode($pngBytes)]],
        ]),
    ]);

    $target = new CapturingContentTarget;

    $topic = makeDraftedTopicWithArticle();

    (new GenerateCoverImageJob($topic->id))->handle(
        app(ImageManager::class),
        app(PromptBuilder::class),
        $target,
        app('config'),
        app(LlmClient::class),
    );

    expect($target->coversCaptured)->toHaveCount(1)
        ->and($target->coversCaptured[0]['image']->bytes)->toBe($pngBytes)
        ->and($target->coversCaptured[0]['image']->sourceDriver)->toBe('imagen')
        ->and($target->coversCaptured[0]['image']->width)->toBe(1792)
        ->and($target->coversCaptured[0]['image']->height)->toBe(1024)
        ->and($target->coversCaptured[0]['image']->prompt)->toContain('Tbilisi reservoir');
});

it('honors driverOverride and routes through with()', function () {
    Http::fake([
        'api.unsplash.com/search/photos*' => Http::response([
            'results' => [[
                'urls' => ['raw' => 'https://images.unsplash.com/photo-x'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/x/download'],
                'user' => ['name' => 'Photographer', 'links' => ['html' => 'https://unsplash.com/@x']],
            ]],
        ]),
        'images.unsplash.com/*' => Http::response('IMG', 200, ['Content-Type' => 'image/jpeg']),
        'api.unsplash.com/photos/*/download' => Http::response('', 200),
    ]);

    $target = new CapturingContentTarget;
    $topic = makeDraftedTopicWithArticle();

    (new GenerateCoverImageJob($topic->id, driverOverride: 'unsplash'))->handle(
        app(ImageManager::class),
        app(PromptBuilder::class),
        $target,
        app('config'),
        app(LlmClient::class),
    );

    expect($target->coversCaptured)->toHaveCount(1)
        ->and($target->coversCaptured[0]['image']->sourceDriver)->toBe('unsplash')
        ->and($target->coversCaptured[0]['image']->attribution)->toContain('Photo by Photographer');
});

it('logs and continues silently when the driver throws', function () {
    // ContentTarget container binding is required by the text-only fallback
    // notification (it builds the admin URL). Bind a capturing target so the
    // fallback path can resolve it without exploding.
    app()->instance(ContentTarget::class, new CapturingContentTarget);

    Log::shouldReceive('channel')->with('daily')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with(
        'stringer.generate_cover_image_job.failed',
        Mockery::on(fn ($ctx) => $ctx['driver'] === 'imagen' && is_string($ctx['error'])),
    );
    // The fallback text-only notification fires unrelated Http requests
    // through the TelegramClient; we let those go through to Http::fake().
    Log::shouldReceive('warning')->withAnyArgs();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $target = new CapturingContentTarget;
    $topic = makeDraftedTopicWithArticle();

    (new GenerateCoverImageJob($topic->id))->handle(
        app(ImageManager::class),
        app(PromptBuilder::class),
        $target,
        app('config'),
        app(LlmClient::class),
    );

    expect($target->coversCaptured)->toBeEmpty();
});

it('bails out when regenerateCount exceeds max_regenerates_per_topic', function () {
    Log::shouldReceive('channel')->with('daily')->andReturnSelf();
    Log::shouldReceive('info')->once()->with(
        'stringer.generate_cover_image_job.regenerate_ceiling_hit',
        Mockery::on(fn ($ctx) => $ctx['attempt'] === 5 && $ctx['max'] === 3),
    );

    $target = new CapturingContentTarget;
    $topic = makeDraftedTopicWithArticle();

    (new GenerateCoverImageJob($topic->id, regenerateCount: 5))->handle(
        app(ImageManager::class),
        app(PromptBuilder::class),
        $target,
        app('config'),
        app(LlmClient::class),
    );

    expect($target->coversCaptured)->toBeEmpty();
});

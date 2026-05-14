<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Jobs\AutoGenerateWeeklyJob;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Tests\Doubles\StaticContextBuilder;

uses(RefreshDatabase::class);

it('dispatches GenerateDraftJob for the oldest Queued topic when one exists', function () {
    Bus::fake();

    $oldest = (new TopicQueue)->enqueue('oldest one', TopicSource::Manual);
    $newer = (new TopicQueue)->enqueue('newer one', TopicSource::Manual);
    // Pin newer's created_at one second ahead so the orderBy is deterministic.
    $newer->created_at = $oldest->created_at->copy()->addSecond();
    $newer->save();

    (new AutoGenerateWeeklyJob)->handle(new TopicQueue, new StaticContextBuilder([]));

    Bus::assertDispatched(GenerateDraftJob::class, fn (GenerateDraftJob $job) => $job->topicId === $oldest->id);
    Bus::assertDispatchedTimes(GenerateDraftJob::class, 1);
});

it('synthesizes an Auto topic from the first project title when no Queued topic exists', function () {
    Bus::fake();

    $context = new StaticContextBuilder([
        'projects' => [
            ['title' => 'Inertia SSR Setup', 'excerpt' => '...'],
            ['title' => 'Filament Plugins', 'excerpt' => '...'],
        ],
    ]);

    (new AutoGenerateWeeklyJob)->handle(new TopicQueue, $context);

    $topic = BlogTopic::query()->first();
    expect($topic)->not->toBeNull()
        ->and($topic->source)->toBe(TopicSource::Auto)
        ->and($topic->status)->toBe(TopicStatus::Queued)
        ->and($topic->hint)->toBe('Write about: Inertia SSR Setup');

    Bus::assertDispatched(GenerateDraftJob::class, fn (GenerateDraftJob $job) => $job->topicId === $topic->id);
});

it('falls back to repositories when projects bucket is empty', function () {
    Bus::fake();

    $context = new StaticContextBuilder([
        'projects' => [],
        'repositories' => [['title' => 'laravel-stringer', 'excerpt' => '...']],
    ]);

    (new AutoGenerateWeeklyJob)->handle(new TopicQueue, $context);

    expect(BlogTopic::query()->first()?->hint)->toBe('Write about: laravel-stringer');
});

it('is a silent no-op when there are no Queued topics and the context is empty', function () {
    Bus::fake();

    (new AutoGenerateWeeklyJob)->handle(new TopicQueue, new StaticContextBuilder([]));

    expect(BlogTopic::count())->toBe(0);
    Bus::assertNotDispatched(GenerateDraftJob::class);
});

it('is a silent no-op when project/repository entries lack titles', function () {
    Bus::fake();

    $context = new StaticContextBuilder([
        'projects' => [['excerpt' => 'no title here']],
        'repositories' => [['title' => '']],
    ]);

    (new AutoGenerateWeeklyJob)->handle(new TopicQueue, $context);

    expect(BlogTopic::count())->toBe(0);
    Bus::assertNotDispatched(GenerateDraftJob::class);
});

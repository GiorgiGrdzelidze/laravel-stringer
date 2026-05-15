<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Jobs\GenerateCoverImageJob;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Tests\Doubles\StubArticle;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('stringer.images.enabled', true);
    config()->set('stringer.models.article', StubArticle::class);

    StubArticle::ensureSchema();
});

it('dispatches a cover job for every drafted topic with an article', function () {
    Bus::fake();

    $a1 = StubArticle::create(['title' => 'A', 'excerpt' => 'a']);
    $a2 = StubArticle::create(['title' => 'B', 'excerpt' => 'b']);

    $t1 = BlogTopic::create(['hint' => 'one', 'status' => TopicStatus::Drafted, 'source' => TopicSource::Manual, 'article_id' => $a1->id]);
    $t2 = BlogTopic::create(['hint' => 'two', 'status' => TopicStatus::Drafted, 'source' => TopicSource::Manual, 'article_id' => $a2->id]);
    BlogTopic::create(['hint' => 'queued', 'status' => TopicStatus::Queued, 'source' => TopicSource::Manual]);

    \Pest\Laravel\artisan('stringer:images:backfill')->assertExitCode(0);

    Bus::assertDispatched(GenerateCoverImageJob::class, fn (GenerateCoverImageJob $job) => $job->topicId === $t1->id);
    Bus::assertDispatched(GenerateCoverImageJob::class, fn (GenerateCoverImageJob $job) => $job->topicId === $t2->id);
    Bus::assertDispatchedTimes(GenerateCoverImageJob::class, 2);
});

it('honors --driver to override the default driver per dispatched job', function () {
    Bus::fake();

    $a = StubArticle::create(['title' => 'A', 'excerpt' => 'a']);
    BlogTopic::create(['hint' => 'one', 'status' => TopicStatus::Drafted, 'source' => TopicSource::Manual, 'article_id' => $a->id]);

    \Pest\Laravel\artisan('stringer:images:backfill', ['--driver' => 'unsplash'])->assertExitCode(0);

    Bus::assertDispatched(
        GenerateCoverImageJob::class,
        fn (GenerateCoverImageJob $job) => $job->driverOverride === 'unsplash'
    );
});

it('rejects unknown driver names', function () {
    Bus::fake();

    \Pest\Laravel\artisan('stringer:images:backfill', ['--driver' => 'mystery'])
        ->expectsOutputToContain("Unknown driver 'mystery'")
        ->assertExitCode(1);

    Bus::assertNotDispatched(GenerateCoverImageJob::class);
});

it('honors --limit', function () {
    Bus::fake();

    for ($i = 0; $i < 5; $i++) {
        $a = StubArticle::create(['title' => 'A'.$i, 'excerpt' => 'a']);
        BlogTopic::create(['hint' => 'h'.$i, 'status' => TopicStatus::Drafted, 'source' => TopicSource::Manual, 'article_id' => $a->id]);
    }

    \Pest\Laravel\artisan('stringer:images:backfill', ['--limit' => 2])->assertExitCode(0);

    Bus::assertDispatchedTimes(GenerateCoverImageJob::class, 2);
});

it('rejects non-positive --limit values', function () {
    Bus::fake();

    \Pest\Laravel\artisan('stringer:images:backfill', ['--limit' => 0])
        ->expectsOutputToContain('--limit must be at least 1')
        ->assertExitCode(1);
});

it('exits cleanly when no eligible topics exist', function () {
    Bus::fake();

    \Pest\Laravel\artisan('stringer:images:backfill')->assertExitCode(0);

    Bus::assertDispatchedTimes(GenerateCoverImageJob::class, 0);
});

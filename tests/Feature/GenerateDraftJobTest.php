<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Database\Seeders\StringerDefaultContentFieldsSeeder;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\DraftGenerator;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Tests\Doubles\CapturingContentTarget;
use Stringer\Laravel\Tests\Doubles\ManagerStub;
use Stringer\Laravel\Tests\Doubles\ScriptedLlmClient;
use Stringer\Laravel\Tests\Doubles\StaticContextBuilder;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('stringer.llm.driver', 'gemini');
    config()->set('stringer.llm.api_keys.gemini', 'test-key');
    config()->set('stringer.llm.models.gemini', 'gemini-2.0-flash');
    config()->set('stringer.locales.primary', 'en');
    config()->set('stringer.locales.list', ['en', 'ka', 'ru']);

    (new StringerDefaultContentFieldsSeeder)->run();
});

function bindDraftGeneratorWithScriptedLlm(ScriptedLlmClient $llm): void
{
    app()->instance(DraftGenerator::class, new DraftGenerator(
        new ManagerStub($llm, 'gemini-2.0-flash'),
        new StaticContextBuilder(['articles' => [], 'projects' => [], 'repositories' => [], 'categories' => []]),
        app(PromptBuilder::class),
        new CapturingContentTarget,
        new TopicQueue,
        /** @var Repository $cfg */
        $cfg = app('config'),
    ));
}

it('runs the generator and leaves the topic Drafted on success', function () {
    $primary = json_encode([
        'title' => 't', 'excerpt' => 'e', 'body' => 'b', 'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    bindDraftGeneratorWithScriptedLlm(new ScriptedLlmClient($primary, array_fill(0, 6, 'tr')));

    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    (new GenerateDraftJob($topic->id))->handle(
        app(DraftGenerator::class),
        app(TopicQueue::class),
    );

    expect($topic->fresh()->status)->toBe(TopicStatus::Drafted);
});

it('skips when the topic is already Drafting and updated_at is within the last 5 minutes', function () {
    bindDraftGeneratorWithScriptedLlm(new ScriptedLlmClient(''));

    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);
    (new TopicQueue)->markDrafting($topic);

    Carbon::setTestNow(now()->addMinutes(2));

    (new GenerateDraftJob($topic->id))->handle(
        app(DraftGenerator::class),
        app(TopicQueue::class),
    );

    // No state change — still Drafting, no Drafted transition, no rows added.
    expect($topic->fresh()->status)->toBe(TopicStatus::Drafting);

    Carbon::setTestNow();
});

it('proceeds when the topic is Drafting but updated_at is older than 5 minutes', function () {
    $primary = json_encode([
        'title' => 't', 'excerpt' => 'e', 'body' => 'b', 'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    bindDraftGeneratorWithScriptedLlm(new ScriptedLlmClient($primary, array_fill(0, 6, 'tr')));

    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);
    (new TopicQueue)->markDrafting($topic);

    Carbon::setTestNow(now()->addMinutes(6));

    (new GenerateDraftJob($topic->id))->handle(
        app(DraftGenerator::class),
        app(TopicQueue::class),
    );

    expect($topic->fresh()->status)->toBe(TopicStatus::Drafted);

    Carbon::setTestNow();
});

it('returns silently when the topic was deleted between dispatch and handle', function () {
    bindDraftGeneratorWithScriptedLlm(new ScriptedLlmClient(''));

    // Use an id that does not exist.
    (new GenerateDraftJob(999_999))->handle(
        app(DraftGenerator::class),
        app(TopicQueue::class),
    );

    expect(BlogTopic::count())->toBe(0);
});

it('marks the topic Failed on final failure and writes a daily-log entry', function () {
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    Log::shouldReceive('channel')->with('daily')->andReturnSelf();
    Log::shouldReceive('error')->once()->with(
        'stringer.generate_draft_job.failed',
        Mockery::on(fn ($context) => $context['topic_id'] === $topic->id
            && $context['error'] === 'translation API timed out'
            && $context['exception'] === RuntimeException::class
        ),
    );

    (new GenerateDraftJob($topic->id))->failed(new RuntimeException('translation API timed out'));

    $fresh = $topic->fresh();

    expect($fresh->status)->toBe(TopicStatus::Failed)
        ->and($fresh->last_error)->toBe('translation API timed out');
});

it('failed() is a no-op for a topic that no longer exists, but still logs', function () {
    Log::shouldReceive('channel')->with('daily')->andReturnSelf();
    Log::shouldReceive('error')->once();

    // No exception even though the topic was never created.
    (new GenerateDraftJob(999_999))->failed(new RuntimeException('boom'));

    expect(BlogTopic::count())->toBe(0);
});

it('declares tries=2 and a 30-second backoff', function () {
    $job = new GenerateDraftJob(1);

    expect($job->tries)->toBe(2)
        ->and($job->backoff())->toBe([30]);
});

it('handle rethrows when the generator throws, so the queue worker can retry', function () {
    bindDraftGeneratorWithScriptedLlm(new ScriptedLlmClient('not valid json'));

    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    expect(fn () => (new GenerateDraftJob($topic->id))->handle(
        app(DraftGenerator::class),
        app(TopicQueue::class),
    ))->toThrow(RuntimeException::class);

    // The handler transitioned to Drafting before the throw, but the
    // generator's transaction rolled back any partial write. failed()
    // would fire on the queue side and mark the topic Failed — exercised
    // by the earlier dedicated test.
    expect($topic->fresh()->status)->toBe(TopicStatus::Drafting);
});

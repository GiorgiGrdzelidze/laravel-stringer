<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\TopicQueue;

uses(RefreshDatabase::class);

it('enqueue creates a Queued topic with the given hint and source', function () {
    $topic = (new TopicQueue)->enqueue('what is laravel scout?', TopicSource::Manual);

    expect($topic->exists)->toBeTrue()
        ->and($topic->status)->toBe(TopicStatus::Queued)
        ->and($topic->source)->toBe(TopicSource::Manual)
        ->and($topic->hint)->toBe('what is laravel scout?')
        ->and($topic->requested_by_chat_id)->toBeNull()
        ->and($topic->source_ref_type)->toBeNull()
        ->and($topic->source_ref_id)->toBeNull();
});

it('enqueue records the Telegram chat id when provided', function () {
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual, chatId: 4242);

    expect($topic->fresh()->requested_by_chat_id)->toBe(4242);
});

it('enqueue associates a polymorphic source reference when provided', function () {
    $sourceTopic = (new TopicQueue)->enqueue('source', TopicSource::Manual);
    $sourceTopic->refresh();

    $linked = (new TopicQueue)->enqueue('linked', TopicSource::Repo, sourceRef: $sourceTopic);

    expect($linked->source_ref_type)->toBe(BlogTopic::class)
        ->and($linked->source_ref_id)->toBe($sourceTopic->id);
});

it('markDrafting transitions a Queued topic and bumps updated_at', function () {
    Carbon::setTestNow('2026-05-14 12:00:00');
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);
    $originalUpdatedAt = $topic->updated_at;

    Carbon::setTestNow('2026-05-14 12:00:05');
    (new TopicQueue)->markDrafting($topic);

    expect($topic->fresh()->status)->toBe(TopicStatus::Drafting)
        ->and($topic->fresh()->updated_at->greaterThan($originalUpdatedAt))->toBeTrue();

    Carbon::setTestNow();
});

it('markDrafted attaches the article + generated_by + drafted_at', function () {
    Carbon::setTestNow('2026-05-14 13:30:00');

    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    // The "article" can be any model with a key for the package's purposes.
    $article = (new TopicQueue)->enqueue('article-shaped', TopicSource::Manual);

    (new TopicQueue)->markDrafted($topic, $article, 'gemini-2.0-flash');

    $fresh = $topic->fresh();

    expect($fresh->status)->toBe(TopicStatus::Drafted)
        ->and($fresh->article_id)->toBe($article->id)
        ->and($fresh->generated_by)->toBe('gemini-2.0-flash')
        ->and($fresh->drafted_at?->toDateTimeString())->toBe('2026-05-14 13:30:00');

    Carbon::setTestNow();
});

it('markFailed records the error message verbatim when short', function () {
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    (new TopicQueue)->markFailed($topic, 'translation API timed out');

    expect($topic->fresh()->status)->toBe(TopicStatus::Failed)
        ->and($topic->fresh()->last_error)->toBe('translation API timed out');
});

it('markFailed truncates last_error to 1000 chars', function () {
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    $long = str_repeat('x', 2_000);
    (new TopicQueue)->markFailed($topic, $long);

    $stored = $topic->fresh()->last_error;

    expect($stored)->not->toBeNull()
        ->and(mb_strlen((string) $stored))->toBe(1000);
});

it('markRejected transitions to Rejected without touching other fields', function () {
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    (new TopicQueue)->markRejected($topic);

    $fresh = $topic->fresh();

    expect($fresh->status)->toBe(TopicStatus::Rejected)
        ->and($fresh->last_error)->toBeNull()
        ->and($fresh->article_id)->toBeNull();
});

it('is bound as a singleton in the container', function () {
    expect(app(TopicQueue::class))->toBe(app(TopicQueue::class));
});

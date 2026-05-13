<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;

uses(RefreshDatabase::class);

it('casts status and source to backed enums', function () {
    $topic = BlogTopic::create([
        'hint' => 'something to write about',
        'status' => TopicStatus::Queued,
        'source' => TopicSource::Manual,
    ]);

    expect($topic->fresh()->status)->toBe(TopicStatus::Queued)
        ->and($topic->fresh()->source)->toBe(TopicSource::Manual);
});

it('persists requested_by_chat_id as an integer', function () {
    $topic = BlogTopic::create([
        'hint' => 'hint',
        'status' => TopicStatus::Queued,
        'source' => TopicSource::Manual,
        'requested_by_chat_id' => 4242,
    ]);

    expect($topic->fresh()->requested_by_chat_id)->toBe(4242);
});

it('allows article_id to be null', function () {
    $topic = BlogTopic::create([
        'hint' => 'hint',
        'status' => TopicStatus::Queued,
        'source' => TopicSource::Manual,
    ]);

    expect($topic->fresh()->article_id)->toBeNull();
});

it('throws when article() is called without the host model configured', function () {
    config()->set('stringer.models.article', null);

    $topic = BlogTopic::create([
        'hint' => 'hint',
        'status' => TopicStatus::Queued,
        'source' => TopicSource::Manual,
    ]);

    expect(fn () => $topic->article())->toThrow(LogicException::class, 'stringer.models.article');
});

it('exposes a sourceRef morphTo relationship', function () {
    $topic = new BlogTopic;

    expect($topic->sourceRef())->toBeInstanceOf(MorphTo::class);
});

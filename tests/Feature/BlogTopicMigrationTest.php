<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Stringer\Laravel\Models\BlogTopic;

uses(RefreshDatabase::class);

it('creates the blog_topics table with the expected columns', function () {
    expect(Schema::hasTable('blog_topics'))->toBeTrue();

    foreach (
        [
            'id',
            'hint',
            'status',
            'source',
            'source_ref_type',
            'source_ref_id',
            'article_id',
            'generated_by',
            'last_error',
            'requested_by_chat_id',
            'auto_publish',
            'target_status',
            'drafted_at',
            'created_at',
            'updated_at',
        ] as $column
    ) {
        expect(Schema::hasColumn('blog_topics', $column))->toBeTrue("missing: {$column}");
    }
});

it('defaults auto_publish to false and target_status to draft on new rows', function () {
    $topic = BlogTopic::create([
        'hint' => 'hint',
        'status' => 'queued',
        'source' => 'manual',
    ]);

    expect($topic->fresh()->auto_publish)->toBeFalse()
        ->and($topic->fresh()->target_status)->toBe('draft');
});

it('uses the configured table name', function () {
    config()->set('stringer.tables.blog_topics', 'stringer_topics_alt');

    expect((new BlogTopic)->getTable())->toBe('stringer_topics_alt');
});

it('rolls back cleanly', function () {
    Schema::dropIfExists('blog_topics');

    expect(Schema::hasTable('blog_topics'))->toBeFalse();
});

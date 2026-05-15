<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerContentField;
use Stringer\Laravel\Models\StringerPrompt;
use Stringer\Laravel\Prompts\DbPromptBuilder;
use Stringer\Laravel\Prompts\DefaultPromptBuilder;

uses(RefreshDatabase::class);

function dbPromptBuilder(): DbPromptBuilder
{
    return new DbPromptBuilder(new DefaultPromptBuilder(new Repository([
        'stringer' => [
            'voice' => ['default' => 'test-voice'],
            'locales' => ['list' => ['en', 'ka', 'ru'], 'primary' => 'en'],
        ],
    ])));
}

function topicFor(string $hint = 'a hint'): BlogTopic
{
    $topic = new BlogTopic;
    $topic->hint = $hint;
    $topic->status = TopicStatus::Queued;
    $topic->source = TopicSource::Manual;

    return $topic;
}

function field(string $name, FieldType $type, string $instruction): StringerContentField
{
    $f = new StringerContentField;
    $f->name = $name;
    $f->type = $type;
    $f->instruction = $instruction;
    $f->is_required = true;

    return $f;
}

it('renders the DB draft template when an active draft row exists', function () {
    StringerPrompt::create([
        'key' => 'draft',
        'locale' => null,
        'content' => 'DB-DRAFT: {{voice}} | {{hint}} | {{source}}',
        'variables' => ['voice', 'hint', 'source'],
        'is_active' => true,
    ]);

    $prompt = dbPromptBuilder()->buildDraftPrompt(
        topicFor('about laravel queues'),
        [],
        [field('title', FieldType::String, 'a title')],
    );

    expect($prompt)->toBe('DB-DRAFT: test-voice | about laravel queues | manual');
});

it('falls back to DefaultPromptBuilder when the draft row is missing', function () {
    expect(StringerPrompt::count())->toBe(0);

    $prompt = dbPromptBuilder()->buildDraftPrompt(
        topicFor('hint'),
        [],
        [field('title', FieldType::String, 'inst')],
    );

    expect($prompt)
        ->toContain('You are a senior staff engineer writing a long-form technical essay')
        ->toContain('test-voice')
        ->toContain('- Source: manual');
});

it('falls back to DefaultPromptBuilder when the draft row is inactive', function () {
    StringerPrompt::create([
        'key' => 'draft',
        'locale' => null,
        'content' => 'IGNORED',
        'variables' => [],
        'is_active' => false,
    ]);

    $prompt = dbPromptBuilder()->buildDraftPrompt(topicFor(), [], []);

    expect($prompt)->toContain('You are a senior staff engineer writing a long-form technical essay');
});

it('prefers a locale-specific translate row over the generic one', function () {
    StringerPrompt::create([
        'key' => 'translate',
        'locale' => null,
        'content' => 'GENERIC: {{target_locale}}::{{english_text}}',
        'variables' => [],
        'is_active' => true,
    ]);

    StringerPrompt::create([
        'key' => 'translate',
        'locale' => 'ka',
        'content' => 'KA-ONLY: {{english_text}}',
        'variables' => [],
        'is_active' => true,
    ]);

    expect(dbPromptBuilder()->buildTranslationPrompt('Hello', 'ka'))
        ->toBe('KA-ONLY: Hello')
        ->and(dbPromptBuilder()->buildTranslationPrompt('Hello', 'ru'))
        ->toBe('GENERIC: ru::Hello');
});

it('falls back to DefaultPromptBuilder for translation when no row matches', function () {
    expect(StringerPrompt::count())->toBe(0);

    $prompt = dbPromptBuilder()->buildTranslationPrompt('Hello', 'ru');

    expect($prompt)
        ->toContain('Translate the text below from English into ru.')
        ->toContain('Hello');
});

it('reads the cover_image template from the DB when one is active', function () {
    StringerPrompt::create([
        'key' => 'cover_image',
        'locale' => null,
        'content' => 'COVER[{{title}}|{{excerpt}}|{{style}}]',
        'variables' => [],
        'is_active' => true,
    ]);

    expect(dbPromptBuilder()->buildImagePrompt('T', 'E', 'minimal'))
        ->toBe('COVER[T|E|minimal]');
});

it('falls back to DefaultPromptBuilder for cover_image when no row matches', function () {
    expect(StringerPrompt::count())->toBe(0);

    $prompt = dbPromptBuilder()->buildImagePrompt('A title', 'An excerpt', 'editorial');

    expect($prompt)
        ->toContain('A title')
        ->toContain('An excerpt')
        ->toContain('Style: editorial');
});

it('ignores inactive cover_image rows and falls through to the default', function () {
    StringerPrompt::create([
        'key' => 'cover_image',
        'locale' => null,
        'content' => 'INACTIVE-WONT-APPEAR',
        'variables' => [],
        'is_active' => false,
    ]);

    $prompt = dbPromptBuilder()->buildImagePrompt('T', 'E', 's');

    expect($prompt)->not->toContain('INACTIVE-WONT-APPEAR');
});

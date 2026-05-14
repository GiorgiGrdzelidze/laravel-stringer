<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Stringer\Laravel\Database\Seeders\StringerDefaultContentFieldsSeeder;
use Stringer\Laravel\Database\Seeders\StringerDefaultPromptsSeeder;
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Models\StringerContentField;
use Stringer\Laravel\Models\StringerPrompt;

uses(RefreshDatabase::class);

it('seeds the five baseline content fields with the expected shape', function () {
    expect(StringerContentField::count())->toBe(0);

    (new StringerDefaultContentFieldsSeeder)->run();

    $fields = StringerContentField::query()->orderBy('sort_order')->get();

    expect($fields)->toHaveCount(5)
        ->and($fields->pluck('name')->all())->toBe(['title', 'excerpt', 'body', 'tags', 'category']);

    /** @var StringerContentField $title */
    $title = $fields->firstWhere('name', 'title');
    expect($title->type)->toBe(FieldType::TranslatableString)
        ->and($title->locales)->toBe(['en', 'ka', 'ru'])
        ->and($title->max_length)->toBe(120)
        ->and($title->is_required)->toBeTrue();

    /** @var StringerContentField $body */
    $body = $fields->firstWhere('name', 'body');
    expect($body->type)->toBe(FieldType::TranslatableMarkdown)
        ->and($body->max_words)->toBe(800);

    /** @var StringerContentField $tags */
    $tags = $fields->firstWhere('name', 'tags');
    expect($tags->type)->toBe(FieldType::TagList)
        ->and($tags->min)->toBe(3)
        ->and($tags->max)->toBe(5)
        ->and($tags->locales)->toBeNull();

    /** @var StringerContentField $category */
    $category = $fields->firstWhere('name', 'category');
    expect($category->type)->toBe(FieldType::Category)
        ->and($category->is_required)->toBeFalse();
});

it('does not re-seed when content_field rows already exist', function () {
    StringerContentField::query()->insert([
        'name' => 'existing',
        'type' => FieldType::String->value,
        'instruction' => 'pre-existing row',
        'is_required' => true,
        'sort_order' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new StringerDefaultContentFieldsSeeder)->run();

    expect(StringerContentField::count())->toBe(1);
});

it('seeds the two baseline prompt templates', function () {
    expect(StringerPrompt::count())->toBe(0);

    (new StringerDefaultPromptsSeeder)->run();

    $prompts = StringerPrompt::query()->orderBy('key')->get();

    expect($prompts->pluck('key')->all())->toBe(['draft', 'translate'])
        ->and($prompts->every(fn (StringerPrompt $p) => $p->is_active === true))->toBeTrue()
        ->and($prompts->firstWhere('key', 'draft')?->variables)
        ->toBe(['voice', 'source', 'hint', 'context', 'categories', 'field_schema'])
        ->and($prompts->firstWhere('key', 'translate')?->variables)
        ->toBe(['english_text', 'target_locale']);
});

it('does not re-seed when prompt rows already exist', function () {
    StringerPrompt::query()->insert([
        'key' => 'pre-existing',
        'content' => 'x',
        'variables' => json_encode([]),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new StringerDefaultPromptsSeeder)->run();

    expect(StringerPrompt::count())->toBe(1);
});

it('exposes the active scope on content fields', function () {
    (new StringerDefaultContentFieldsSeeder)->run();

    StringerContentField::query()->where('name', 'tags')->update(['is_active' => false]);

    expect(StringerContentField::active()->ordered()->get()->pluck('name')->all())
        ->toBe(['title', 'excerpt', 'body', 'category']);
});

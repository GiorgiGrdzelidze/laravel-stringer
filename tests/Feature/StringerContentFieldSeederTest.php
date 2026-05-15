<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stringer\Laravel\Database\Seeders\StringerDefaultContentFieldsSeeder;
use Stringer\Laravel\Database\Seeders\StringerDefaultPromptsSeeder;
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Models\StringerContentField;
use Stringer\Laravel\Models\StringerPrompt;

uses(RefreshDatabase::class);

it('seeds the twelve baseline content fields with the expected shape', function () {
    expect(StringerContentField::count())->toBe(0);

    (new StringerDefaultContentFieldsSeeder)->run();

    $fields = StringerContentField::query()->orderBy('sort_order')->get();

    expect($fields)->toHaveCount(12)
        ->and($fields->pluck('name')->all())->toBe([
            'title', 'excerpt', 'slug', 'body',
            'meta_title', 'meta_description',
            'og_title', 'og_description',
            'twitter_title', 'twitter_description',
            'tags', 'category',
        ]);

    foreach (['og_title', 'og_description', 'twitter_title', 'twitter_description'] as $name) {
        /** @var StringerContentField $field */
        $field = $fields->firstWhere('name', $name);
        expect($field->type)->toBe(FieldType::TranslatableString)
            ->and($field->locales)->toBe(['en', 'ka', 'ru'])
            ->and($field->is_required)->toBeFalse();
    }

    /** @var StringerContentField $slug */
    $slug = $fields->firstWhere('name', 'slug');
    expect($slug->type)->toBe(FieldType::TranslatableString)
        ->and($slug->locales)->toBe(['en', 'ka', 'ru'])
        ->and($slug->max_length)->toBe(60)
        ->and($slug->is_required)->toBeFalse();

    /** @var StringerContentField $title */
    $title = $fields->firstWhere('name', 'title');
    expect($title->type)->toBe(FieldType::TranslatableString)
        ->and($title->locales)->toBe(['en', 'ka', 'ru'])
        ->and($title->max_length)->toBe(70)
        ->and($title->is_required)->toBeTrue();

    /** @var StringerContentField $body */
    $body = $fields->firstWhere('name', 'body');
    expect($body->type)->toBe(FieldType::TranslatableMarkdown)
        ->and($body->max_words)->toBe(2500);

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

it('does not re-seed content_field rows whose name already exists, but does add missing names', function () {
    // Operator-edited 'title' row already in DB — must not be overwritten.
    StringerContentField::query()->insert([
        'name' => 'title',
        'type' => FieldType::String->value,
        'instruction' => 'OPERATOR EDIT — do not overwrite',
        'is_required' => true,
        'sort_order' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new StringerDefaultContentFieldsSeeder)->run();

    // Pre-existing 'title' is untouched; the other 11 baseline rows are
    // freshly inserted alongside it.
    expect(StringerContentField::count())->toBe(12)
        ->and(StringerContentField::where('name', 'title')->first()?->instruction)
        ->toBe('OPERATOR EDIT — do not overwrite');
});

it('seeds the three baseline prompt templates', function () {
    expect(StringerPrompt::count())->toBe(0);

    (new StringerDefaultPromptsSeeder)->run();

    $prompts = StringerPrompt::query()->orderBy('key')->get();

    expect($prompts->pluck('key')->all())->toBe(['cover_image', 'draft', 'translate'])
        ->and($prompts->every(fn (StringerPrompt $p) => $p->is_active === true))->toBeTrue()
        ->and($prompts->firstWhere('key', 'draft')?->variables)
        ->toBe(['voice', 'source', 'hint', 'context', 'categories', 'field_schema'])
        ->and($prompts->firstWhere('key', 'translate')?->variables)
        ->toBe(['english_text', 'target_locale'])
        ->and($prompts->firstWhere('key', 'cover_image')?->variables)
        ->toBe(['title', 'excerpt', 'style']);
});

it('does not re-seed prompt rows whose key+locale already exists', function () {
    // Operator-edited 'draft' row already in DB.
    StringerPrompt::query()->insert([
        'key' => 'draft',
        'locale' => null,
        'content' => 'OPERATOR EDIT — do not overwrite',
        'variables' => json_encode([]),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new StringerDefaultPromptsSeeder)->run();

    // The pre-existing 'draft' row stays untouched; missing 'translate' and
    // 'cover_image' rows get added → 3 total.
    $rows = StringerPrompt::query()->orderBy('key')->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('key')->all())->toBe(['cover_image', 'draft', 'translate'])
        ->and($rows->firstWhere('key', 'draft')?->content)->toBe('OPERATOR EDIT — do not overwrite');
});

it('exposes the active scope on content fields', function () {
    (new StringerDefaultContentFieldsSeeder)->run();

    StringerContentField::query()->where('name', 'tags')->update(['is_active' => false]);

    expect(StringerContentField::active()->ordered()->get()->pluck('name')->all())
        ->toBe([
            'title', 'excerpt', 'slug', 'body',
            'meta_title', 'meta_description',
            'og_title', 'og_description',
            'twitter_title', 'twitter_description',
            'category',
        ]);
});

it('rejects duplicate content_field names via the unique index', function () {
    StringerContentField::query()->insert([
        'name' => 'title',
        'type' => FieldType::String->value,
        'instruction' => 'first',
        'is_required' => true,
        'sort_order' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => StringerContentField::query()->insert([
        'name' => 'title',
        'type' => FieldType::String->value,
        'instruction' => 'duplicate',
        'is_required' => true,
        'sort_order' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('honors config-driven table names for StringerPrompt', function () {
    config()->set('stringer.tables.stringer_prompts', 'stringer_prompts_alt');

    expect((new StringerPrompt)->getTable())->toBe('stringer_prompts_alt');
});

it('honors config-driven table names for StringerContentField', function () {
    config()->set('stringer.tables.stringer_content_fields', 'stringer_content_fields_alt');

    expect((new StringerContentField)->getTable())->toBe('stringer_content_fields_alt');
});

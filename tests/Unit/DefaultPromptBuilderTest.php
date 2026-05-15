<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerContentField;
use Stringer\Laravel\Prompts\DefaultPromptBuilder;

function defaultPromptBuilderConfig(string $voice = 'first-person, dry, technical confidence'): Repository
{
    return new Repository([
        'stringer' => [
            'voice' => ['default' => $voice],
            'locales' => ['list' => ['en', 'ka', 'ru'], 'primary' => 'en'],
        ],
    ]);
}

/**
 * @param  list<string>|null  $locales
 */
function unsavedField(
    string $name,
    FieldType $type,
    string $instruction,
    bool $required = true,
    ?int $maxLength = null,
    ?int $maxWords = null,
    ?int $min = null,
    ?int $max = null,
    ?array $locales = null,
): StringerContentField {
    $field = new StringerContentField;
    $field->name = $name;
    $field->type = $type;
    $field->instruction = $instruction;
    $field->is_required = $required;
    $field->max_length = $maxLength;
    $field->max_words = $maxWords;
    $field->min = $min;
    $field->max = $max;
    $field->locales = $locales;

    return $field;
}

function unsavedTopic(string $hint = 'what is laravel scout?'): BlogTopic
{
    $topic = new BlogTopic;
    $topic->hint = $hint;
    $topic->status = TopicStatus::Queued;
    $topic->source = TopicSource::Manual;

    return $topic;
}

it('substitutes voice, hint, source into the draft prompt', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig('first-person, dry, technical confidence'));

    $prompt = $builder->buildDraftPrompt(
        unsavedTopic('what is laravel scout?'),
        [],
        [unsavedField('title', FieldType::TranslatableString, 'SEO title.')],
    );

    expect($prompt)
        ->toContain('first-person, dry, technical confidence')
        ->toContain('what is laravel scout?')
        ->toContain('- Source: manual')
        ->toContain('- title [translatable_string, required]: SEO title.');

    expect(str_contains($prompt, '{{'))->toBeFalse();
});

it('formats the field schema with constraints inline', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig());

    $fields = [
        unsavedField('title', FieldType::TranslatableString, 'A title.', true, 120, null, null, null, ['en', 'ka']),
        unsavedField('body', FieldType::TranslatableMarkdown, 'A body.', true, null, 800, null, null, ['en']),
        unsavedField('tags', FieldType::TagList, 'Tags.', false, null, null, 3, 5),
    ];

    $prompt = $builder->buildDraftPrompt(unsavedTopic(), [], $fields);

    expect($prompt)
        ->toContain('- title [translatable_string, required] (locales=en/ka, max_length=120): A title.')
        ->toContain('- body [translatable_markdown, required] (locales=en, max_words=800): A body.')
        ->toContain('- tags [tag_list, optional] (min=3, max=5): Tags.');
});

it('formats categories and recent reference content when provided', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig());

    $context = [
        'articles' => [['title' => 'Past Post', 'excerpt' => 'A summary.']],
        'categories' => [
            ['name' => 'Backend', 'slug' => 'backend', 'description' => 'Server-side topics.'],
            ['name' => 'DevOps', 'slug' => 'devops'],
        ],
    ];

    $prompt = $builder->buildDraftPrompt(unsavedTopic(), $context, []);

    expect($prompt)
        ->toContain('## articles')
        ->toContain('- Past Post — A summary.')
        ->toContain('- backend: Backend — Server-side topics.')
        ->toContain('- devops: DevOps');
});

it('falls back to a placeholder phrase when no context or categories are available', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig());

    $prompt = $builder->buildDraftPrompt(unsavedTopic(), [], []);

    expect($prompt)
        ->toContain('(no reference content available)')
        ->toContain('(no categories defined; output null for category)')
        ->toContain('(no fields configured)');
});

it('substitutes target_locale and english_text into the translation prompt', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig());

    $prompt = $builder->buildTranslationPrompt('Hello, world.', 'ka');

    expect($prompt)
        ->toContain('Translate the text below from English into ka.')
        ->toContain('Hello, world.');

    expect(str_contains($prompt, '{{'))->toBeFalse();
});

it('renderDraft and renderTranslation work with arbitrary template strings', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig('terse, no-nonsense'));

    $draftTemplate = 'Voice: {{voice}}. Hint: {{hint}}. Source: {{source}}.';
    $translateTemplate = '{{target_locale}}::{{english_text}}';

    $rendered = $builder->renderDraft($draftTemplate, unsavedTopic('topic-X'), [], []);
    expect($rendered)->toBe('Voice: terse, no-nonsense. Hint: topic-X. Source: manual.');

    $translated = $builder->renderTranslation($translateTemplate, 'hi', 'ru');
    expect($translated)->toBe('ru::hi');
});

it('substitutes title, excerpt, and style into the image prompt', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig());

    $prompt = $builder->buildImagePrompt(
        'Spotting and fixing N+1 query bugs in Eloquent',
        'A 4.2-second category page, 247 queries behind it, and the eager-loading fix.',
        'editorial illustration, muted palette, no text, no logos',
    );

    expect($prompt)
        ->toContain('Spotting and fixing N+1 query bugs in Eloquent')
        ->toContain('A 4.2-second category page')
        ->toContain('Style: editorial illustration, muted palette, no text, no logos');

    expect(str_contains($prompt, '{{'))->toBeFalse();
});

it('renderImage() works with arbitrary template strings', function () {
    $builder = new DefaultPromptBuilder(defaultPromptBuilderConfig());

    $template = '[{{title}}] {{excerpt}} ({{style}})';

    $rendered = $builder->renderImage($template, 'My Title', 'My Excerpt', 'minimalist');

    expect($rendered)->toBe('[My Title] My Excerpt (minimalist)');
});

<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Database\Seeders\StringerDefaultContentFieldsSeeder;
use Stringer\Laravel\DataObjects\LocalizedDraft;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Llm\LlmManager;
use Stringer\Laravel\Models\StringerPrompt;
use Stringer\Laravel\Services\AiTellSanitizer;
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

    // Seed the default field schema (title, excerpt, body, tags, category).
    (new StringerDefaultContentFieldsSeeder)->run();
});

/**
 * @param  array<string, mixed>|null  $contextOverride
 * @return array{LlmManager, TopicQueue, DraftGenerator, CapturingContentTarget, ScriptedLlmClient}
 */
function makeGenerator(ScriptedLlmClient $llm, ?array $contextOverride = null): array
{
    $manager = new ManagerStub($llm, 'gemini-2.0-flash');
    $context = new StaticContextBuilder($contextOverride ?? [
        'articles' => [],
        'projects' => [],
        'repositories' => [],
        'categories' => [],
    ]);
    $target = new CapturingContentTarget;
    $promptBuilder = app(PromptBuilder::class);
    $topicQueue = new TopicQueue;

    /** @var Repository $config */
    $config = app('config');

    $generator = new DraftGenerator(
        $manager,
        $context,
        $promptBuilder,
        $target,
        $topicQueue,
        $config,
        new AiTellSanitizer,
    );

    return [$manager, $topicQueue, $generator, $target, $llm];
}

it('makes one draft call + one translate call per non-primary locale per translatable field', function () {
    $primary = json_encode([
        'title' => 'Laravel Scout Deep Dive',
        'excerpt' => 'A quick intro to Scout indexing.',
        'body' => 'Body content in EN markdown.',
        'tags' => ['laravel', 'scout', 'search'],
        'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    // 3 translatable fields × 2 non-primary locales = 6 translate calls.
    $translations = array_fill(0, 6, 'TRANSLATED');

    $llm = new ScriptedLlmClient($primary, $translations);
    [, , $generator, $target, $client] = makeGenerator($llm);

    $topic = (new TopicQueue)->enqueue('what is laravel scout?', TopicSource::Manual);

    $result = $generator->generate($topic);

    expect($client->draftCalls)->toBe(1)
        ->and(count($client->translateCalls))->toBe(6)
        ->and($target->wasCalled)->toBeTrue()
        ->and($target->capturedDraft)->toBeInstanceOf(LocalizedDraft::class)
        ->and($target->capturedTopic?->id)->toBe($topic->id)
        ->and($result->exists)->toBeTrue();
});

it('writes a LocalizedDraft keyed by field name with locale arrays for translatable fields and scalars for the rest', function () {
    $primary = json_encode([
        'title' => 'EN Title',
        'excerpt' => 'EN Excerpt',
        'body' => 'EN Body',
        'tags' => ['a', 'b', 'c'],
        'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    $llm = new ScriptedLlmClient($primary, [
        'KA Title', 'RU Title',
        'KA Excerpt', 'RU Excerpt',
        'KA Body', 'RU Body',
    ]);

    [, , $generator, $target] = makeGenerator($llm);

    $generator->generate((new TopicQueue)->enqueue('hint', TopicSource::Manual));

    $draft = $target->capturedDraft;
    if (! $draft instanceof LocalizedDraft) {
        throw new RuntimeException('capturedDraft should be set after a successful generate()');
    }
    $fields = $draft->fields;

    expect($fields['title'])->toBe(['en' => 'EN Title', 'ka' => 'KA Title', 'ru' => 'RU Title'])
        ->and($fields['excerpt'])->toBe(['en' => 'EN Excerpt', 'ka' => 'KA Excerpt', 'ru' => 'RU Excerpt'])
        ->and($fields['body'])->toBe(['en' => 'EN Body', 'ka' => 'KA Body', 'ru' => 'RU Body'])
        ->and($fields['tags'])->toBe(['a', 'b', 'c'])
        ->and($fields['category'])->toBe('backend');
});

it('marks the topic Drafted with the article id and generated_by after a successful run', function () {
    $primary = json_encode([
        'title' => 't', 'excerpt' => 'e', 'body' => 'b', 'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    $llm = new ScriptedLlmClient($primary, array_fill(0, 6, 'tr'));
    [, , $generator] = makeGenerator($llm);
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    $result = $generator->generate($topic);

    $fresh = $topic->fresh();
    expect($fresh->status)->toBe(TopicStatus::Drafted)
        ->and($fresh->article_id)->toBe($result->getKey())
        ->and($fresh->generated_by)->toBe('gemini-2.0-flash');
});

it('rolls back when the LLM returns invalid JSON and never reaches ContentTarget::write', function () {
    $llm = new ScriptedLlmClient('not a JSON object');
    [, , $generator, $target] = makeGenerator($llm);
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    expect(fn () => $generator->generate($topic))
        ->toThrow(RuntimeException::class, 'LLM response is not valid JSON');

    expect($target->wasCalled)->toBeFalse()
        ->and($topic->fresh()->status)->toBe(TopicStatus::Queued);
});

it('rolls back when a required field is missing from the LLM response', function () {
    $primary = json_encode([
        // intentionally missing 'body' (required)
        'title' => 't', 'excerpt' => 'e', 'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    $llm = new ScriptedLlmClient($primary);
    [, , $generator, $target] = makeGenerator($llm);
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    expect(fn () => $generator->generate($topic))
        ->toThrow(RuntimeException::class, "missing required field 'body'");

    expect($target->wasCalled)->toBeFalse()
        ->and($topic->fresh()->status)->toBe(TopicStatus::Queued);
});

it('rolls back when a translation call fails', function () {
    $primary = json_encode([
        'title' => 't', 'excerpt' => 'e', 'body' => 'b', 'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    // Empty translate queue → ScriptedLlmClient throws on first translate.
    $llm = new ScriptedLlmClient($primary, []);
    [, , $generator, $target] = makeGenerator($llm);
    $topic = (new TopicQueue)->enqueue('hint', TopicSource::Manual);

    expect(fn () => $generator->generate($topic))
        ->toThrow(RuntimeException::class, 'translateQueue exhausted');

    expect($target->wasCalled)->toBeFalse()
        ->and($topic->fresh()->status)->toBe(TopicStatus::Queued);
});

it('skips translation entirely when only the primary locale is configured', function () {
    config()->set('stringer.locales.list', ['en']);

    $primary = json_encode([
        'title' => 't', 'excerpt' => 'e', 'body' => 'b', 'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    $llm = new ScriptedLlmClient($primary);
    [, , $generator, $target, $client] = makeGenerator($llm);

    $generator->generate((new TopicQueue)->enqueue('hint', TopicSource::Manual));

    // Sanitizer capitalises the first letter after sentence boundaries; a
    // single-letter fixture like 't' becomes 'T'.
    expect($client->translateCalls)->toBe([])
        ->and($target->capturedDraft?->field('title'))->toBe(['en' => 'T']);
});

it('passes the PromptBuilder output to LlmClient::translate verbatim (no double wrapping)', function () {
    StringerPrompt::create([
        'key' => 'translate',
        'locale' => null,
        'content' => 'TRANSLATE-{{target_locale}}::{{english_text}}',
        'variables' => [],
        'is_active' => true,
    ]);

    $primary = json_encode([
        'title' => 'EN Title', 'excerpt' => 'EN Excerpt', 'body' => 'EN Body',
        'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR);

    $llm = new ScriptedLlmClient($primary, array_fill(0, 6, 'TR'));
    [, , $generator, , $client] = makeGenerator($llm);

    $generator->generate((new TopicQueue)->enqueue('hint', TopicSource::Manual));

    // The driver should have received the PromptBuilder's rendered template,
    // not a doubly-wrapped string. With the DB template above + the seeded
    // title field's primary value 'EN Title', the first translate call to
    // 'ka' must equal 'TRANSLATE-ka::EN Title'.
    $first = $client->translateCalls[0] ?? null;
    expect($first)->not->toBeNull()
        ->and($first['to'])->toBe('ka')
        ->and($first['text'])->toBe('TRANSLATE-ka::EN Title');
});

it('strips code fences around the LLM JSON response', function () {
    $primary = "```json\n".json_encode([
        'title' => 'T', 'excerpt' => 'E', 'body' => 'B', 'tags' => ['x', 'y', 'z'], 'category' => 'backend',
    ], JSON_THROW_ON_ERROR)."\n```";

    $llm = new ScriptedLlmClient($primary, array_fill(0, 6, 'tr'));
    [, , $generator, $target] = makeGenerator($llm);

    $generator->generate((new TopicQueue)->enqueue('hint', TopicSource::Manual));

    // Sanitizer capitalises the first letter after sentence boundaries — 'tr' → 'Tr'.
    expect($target->capturedDraft?->field('title'))->toBe(['en' => 'T', 'ka' => 'Tr', 'ru' => 'Tr']);
});

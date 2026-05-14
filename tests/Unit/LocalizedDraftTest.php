<?php

declare(strict_types=1);

use Stringer\Laravel\DataObjects\LocalizedDraft;

it('exposes fields verbatim via the public property', function () {
    $fields = [
        'title' => ['en' => 'Title EN', 'ka' => 'სათაური', 'ru' => 'Заголовок'],
        'tags' => ['laravel', 'php', 'queues'],
        'category' => 'backend',
    ];

    $draft = new LocalizedDraft($fields);

    expect($draft->fields)->toBe($fields);
});

it('returns the value for a present field via field()', function () {
    $draft = new LocalizedDraft([
        'title' => ['en' => 'Title'],
        'tags' => ['a', 'b'],
    ]);

    expect($draft->field('title'))->toBe(['en' => 'Title'])
        ->and($draft->field('tags'))->toBe(['a', 'b']);
});

it('throws when asking for a field that is not in the draft', function () {
    $draft = new LocalizedDraft(['title' => ['en' => 'Title']]);

    expect(fn () => $draft->field('excerpt'))
        ->toThrow(InvalidArgumentException::class, "Field 'excerpt' is not in the draft.");
});

it('has() reports presence without throwing', function () {
    $draft = new LocalizedDraft(['title' => ['en' => 'T']]);

    expect($draft->has('title'))->toBeTrue()
        ->and($draft->has('missing'))->toBeFalse();
});

it('names() preserves insertion order', function () {
    $draft = new LocalizedDraft([
        'title' => 'a',
        'excerpt' => 'b',
        'body' => 'c',
        'tags' => [],
        'category' => null,
    ]);

    expect($draft->names())->toBe(['title', 'excerpt', 'body', 'tags', 'category']);
});

it('accepts an empty fields array (no implicit minimum)', function () {
    $draft = new LocalizedDraft([]);

    expect($draft->fields)->toBe([])
        ->and($draft->names())->toBe([])
        ->and($draft->has('anything'))->toBeFalse();
});

it('is final readonly', function () {
    $reflection = new ReflectionClass(LocalizedDraft::class);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($reflection->isFinal())->toBeTrue();
});

<?php

declare(strict_types=1);

use Stringer\Laravel\DataObjects\LocalizedDraft;

it('rejects an empty translation set', function () {
    expect(fn () => new LocalizedDraft([], 'en'))
        ->toThrow(InvalidArgumentException::class, 'at least one translation');
});

it('rejects a primary locale that is not in the translation set', function () {
    $translations = [
        'ka' => ['title' => 't', 'excerpt' => 'e', 'body' => 'b'],
    ];

    expect(fn () => new LocalizedDraft($translations, 'en'))
        ->toThrow(InvalidArgumentException::class, "Primary locale 'en'");
});

it('returns the payload for a locale present in the draft', function () {
    $payload = ['title' => 'Title', 'excerpt' => 'Excerpt', 'body' => 'Body'];
    $draft = new LocalizedDraft(['en' => $payload], 'en');

    expect($draft->forLocale('en'))->toBe($payload);
});

it('throws when asking for a locale that is not in the draft', function () {
    $draft = new LocalizedDraft(
        ['en' => ['title' => 't', 'excerpt' => 'e', 'body' => 'b']],
        'en',
    );

    expect(fn () => $draft->forLocale('ka'))
        ->toThrow(InvalidArgumentException::class, "Locale 'ka'");
});

it('lists locales in insertion order', function () {
    $draft = new LocalizedDraft(
        [
            'en' => ['title' => '', 'excerpt' => '', 'body' => ''],
            'ka' => ['title' => '', 'excerpt' => '', 'body' => ''],
            'ru' => ['title' => '', 'excerpt' => '', 'body' => ''],
        ],
        'en',
    );

    expect($draft->locales())->toBe(['en', 'ka', 'ru']);
});

it('is immutable — translations are exposed as a readonly property', function () {
    $draft = new LocalizedDraft(
        ['en' => ['title' => '', 'excerpt' => '', 'body' => '']],
        'en',
    );

    $reflection = new ReflectionClass($draft);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($reflection->isFinal())->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\Llm\Drivers\GeminiClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts the prompt verbatim to the Gemini generateContent endpoint', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'drafted body']]]]],
        ]),
    ]);

    $promptFromBuilder = 'Pre-built prompt from PromptBuilder. Voice: dry. Hint: laravel queues.';

    $client = new GeminiClient('test-key', 'gemini-2.0-flash');
    $result = $client->draft($promptFromBuilder, []);

    expect($result)->toBe('drafted body');

    Http::assertSent(function (Request $request) use ($promptFromBuilder) {
        if ($request->method() !== 'POST') {
            return false;
        }

        if (! str_contains($request->url(), 'v1beta/models/gemini-2.0-flash:generateContent')) {
            return false;
        }

        if (! str_contains($request->url(), 'key=test-key')) {
            return false;
        }

        $message = data_get($request->data(), 'contents.0.parts.0.text');

        return $message === $promptFromBuilder;
    });
});

it('passes the translation prompt verbatim — does not add its own wrapping', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'თარგმანი']]]]],
        ]),
    ]);

    $promptFromBuilder = 'Translate the following text from English to ka...\n\nHello';

    $client = new GeminiClient('test-key', 'gemini-2.0-flash');

    expect($client->translate($promptFromBuilder, 'en', 'ka'))->toBe('თარგმანი');

    Http::assertSent(function (Request $request) use ($promptFromBuilder) {
        $message = data_get($request->data(), 'contents.0.parts.0.text');

        return $message === $promptFromBuilder;
    });
});

it('throws when the response is empty or malformed', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['candidates' => []]),
    ]);

    $client = new GeminiClient('test-key', 'gemini-2.0-flash');

    expect(fn () => $client->draft('prompt', []))
        ->toThrow(RuntimeException::class, 'Gemini returned an empty or malformed response.');
});

it('throws when the API key is unset', function () {
    Http::fake();

    $client = new GeminiClient('', 'gemini-2.0-flash');

    expect(fn () => $client->draft('prompt', []))
        ->toThrow(RuntimeException::class, 'has no API key configured');

    Http::assertNothingSent();
});

it('surfaces HTTP errors from the provider', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'rate limit'], 429),
    ]);

    $client = new GeminiClient('test-key', 'gemini-2.0-flash');

    expect(fn () => $client->draft('prompt', []))
        ->toThrow(RequestException::class);
});

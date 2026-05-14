<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\Llm\Drivers\GroqClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts to the groq openai-compatible endpoint with bearer auth', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'drafted body']]],
        ]),
    ]);

    $client = new GroqClient('test-key', 'llama-3.3-70b-versatile');
    $result = $client->draft('Write a post.', []);

    expect($result)->toBe('drafted body');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'api.groq.com/openai/v1/chat/completions')
            && $request->header('Authorization') === ['Bearer test-key']
            && $request['model'] === 'llama-3.3-70b-versatile'
            && data_get($request->data(), 'messages.0.role') === 'user';
    });
});

it('throws on empty or malformed response', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => []]),
    ]);

    expect(fn () => (new GroqClient('test-key', 'llama-3.3-70b-versatile'))->draft('p', []))
        ->toThrow(RuntimeException::class, 'Groq returned an empty or malformed response.');
});

it('refuses to send without an API key', function () {
    Http::fake();

    expect(fn () => (new GroqClient('', 'llama-3.3-70b-versatile'))->draft('p', []))
        ->toThrow(RuntimeException::class, 'has no API key configured');

    Http::assertNothingSent();
});

it('surfaces HTTP errors', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(['error' => 'rate limit'], 429),
    ]);

    expect(fn () => (new GroqClient('test-key', 'llama-3.3-70b-versatile'))->draft('p', []))
        ->toThrow(RequestException::class);
});

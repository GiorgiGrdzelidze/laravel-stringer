<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\Llm\Drivers\OpenAiClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts to the openai chat completions endpoint with bearer auth', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'drafted body']]],
        ]),
    ]);

    $client = new OpenAiClient('test-key', 'gpt-4o-mini');
    $result = $client->draft('Write a post.', []);

    expect($result)->toBe('drafted body');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'api.openai.com/v1/chat/completions')
            && $request->header('Authorization') === ['Bearer test-key']
            && $request['model'] === 'gpt-4o-mini'
            && data_get($request->data(), 'messages.0.role') === 'user'
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'Write a post.');
    });
});

it('throws on empty or malformed response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => []]),
    ]);

    expect(fn () => (new OpenAiClient('test-key', 'gpt-4o-mini'))->draft('p', []))
        ->toThrow(RuntimeException::class, 'OpenAI returned an empty or malformed response.');
});

it('refuses to send without an API key', function () {
    Http::fake();

    expect(fn () => (new OpenAiClient('', 'gpt-4o-mini'))->draft('p', []))
        ->toThrow(RuntimeException::class, 'has no API key configured');

    Http::assertNothingSent();
});

it('surfaces HTTP errors', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'rate limit'], 429),
    ]);

    expect(fn () => (new OpenAiClient('test-key', 'gpt-4o-mini'))->draft('p', []))
        ->toThrow(RequestException::class);
});

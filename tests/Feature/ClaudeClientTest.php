<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\Llm\Drivers\ClaudeClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts to the anthropic messages endpoint with the expected headers and body', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'drafted body']],
        ]),
    ]);

    $client = new ClaudeClient('test-key', 'claude-sonnet-4-5');
    $result = $client->draft('Write a post about queues.', []);

    expect($result)->toBe('drafted body');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'api.anthropic.com/v1/messages')
            && $request->header('x-api-key') === ['test-key']
            && $request->header('anthropic-version') === ['2023-06-01']
            && $request['model'] === 'claude-sonnet-4-5'
            && is_int($request['max_tokens'])
            && data_get($request->data(), 'messages.0.role') === 'user'
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'Write a post about queues.');
    });
});

it('throws on empty or malformed response', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => []]),
    ]);

    expect(fn () => (new ClaudeClient('test-key', 'claude-sonnet-4-5'))->draft('p', []))
        ->toThrow(RuntimeException::class, 'Claude returned an empty or malformed response.');
});

it('refuses to send without an API key', function () {
    Http::fake();

    expect(fn () => (new ClaudeClient('', 'claude-sonnet-4-5'))->draft('p', []))
        ->toThrow(RuntimeException::class, 'has no API key configured');

    Http::assertNothingSent();
});

it('surfaces HTTP errors', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 503),
    ]);

    expect(fn () => (new ClaudeClient('test-key', 'claude-sonnet-4-5'))->draft('p', []))
        ->toThrow(RequestException::class);
});

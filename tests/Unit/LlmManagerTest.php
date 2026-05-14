<?php

declare(strict_types=1);

use Stringer\Laravel\Llm\Drivers\ClaudeClient;
use Stringer\Laravel\Llm\Drivers\GeminiClient;
use Stringer\Laravel\Llm\Drivers\GroqClient;
use Stringer\Laravel\Llm\Drivers\OpenAiClient;
use Stringer\Laravel\Llm\LlmManager;

$config = function (string $driver): array {
    return [
        'driver' => $driver,
        'api_keys' => [
            'gemini' => 'gemini-key',
            'claude' => 'claude-key',
            'openai' => 'openai-key',
            'groq' => 'groq-key',
        ],
        'models' => [
            'gemini' => 'gemini-2.0-flash',
            'claude' => 'claude-sonnet-4-5',
            'openai' => 'gpt-4o-mini',
            'groq' => 'llama-3.3-70b-versatile',
        ],
    ];
};

it('resolves the gemini driver', function () use ($config) {
    expect((new LlmManager($config('gemini')))->make())->toBeInstanceOf(GeminiClient::class);
});

it('resolves the claude driver', function () use ($config) {
    expect((new LlmManager($config('claude')))->make())->toBeInstanceOf(ClaudeClient::class);
});

it('resolves the openai driver', function () use ($config) {
    expect((new LlmManager($config('openai')))->make())->toBeInstanceOf(OpenAiClient::class);
});

it('resolves the groq driver', function () use ($config) {
    expect((new LlmManager($config('groq')))->make())->toBeInstanceOf(GroqClient::class);
});

it('throws on an unknown driver name', function () use ($config) {
    expect(fn () => (new LlmManager($config('mystery-driver')))->make())
        ->toThrow(InvalidArgumentException::class, "Unknown LLM driver 'mystery-driver'");
});

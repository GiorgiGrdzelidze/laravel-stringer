<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Stringer\Laravel\Llm\Drivers\ClaudeClient;
use Stringer\Laravel\Llm\Drivers\GeminiClient;
use Stringer\Laravel\Llm\Drivers\GroqClient;
use Stringer\Laravel\Llm\Drivers\OpenAiClient;
use Stringer\Laravel\Llm\LlmManager;

$llmConfig = function (string $driver): Repository {
    return new Repository([
        'stringer' => [
            'llm' => [
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
            ],
        ],
    ]);
};

it('resolves the gemini driver', function () use ($llmConfig) {
    expect((new LlmManager($llmConfig('gemini')))->make())->toBeInstanceOf(GeminiClient::class);
});

it('resolves the claude driver', function () use ($llmConfig) {
    expect((new LlmManager($llmConfig('claude')))->make())->toBeInstanceOf(ClaudeClient::class);
});

it('resolves the openai driver', function () use ($llmConfig) {
    expect((new LlmManager($llmConfig('openai')))->make())->toBeInstanceOf(OpenAiClient::class);
});

it('resolves the groq driver', function () use ($llmConfig) {
    expect((new LlmManager($llmConfig('groq')))->make())->toBeInstanceOf(GroqClient::class);
});

it('throws on an unknown driver name', function () use ($llmConfig) {
    expect(fn () => (new LlmManager($llmConfig('mystery-driver')))->make())
        ->toThrow(InvalidArgumentException::class, "Unknown LLM driver 'mystery-driver'");
});

it('re-reads config on every make() so runtime driver swaps take effect', function () use ($llmConfig) {
    $config = $llmConfig('gemini');
    $manager = new LlmManager($config);

    expect($manager->make())->toBeInstanceOf(GeminiClient::class);

    $config->set('stringer.llm.driver', 'claude');

    expect($manager->make())->toBeInstanceOf(ClaudeClient::class);
});

it('exposes the active driver\'s model identifier via modelName()', function () use ($llmConfig) {
    $config = $llmConfig('gemini');
    $manager = new LlmManager($config);

    expect($manager->modelName())->toBe('gemini-2.0-flash');

    $config->set('stringer.llm.driver', 'claude');

    expect($manager->modelName())->toBe('claude-sonnet-4-5');
});

it('modelName() returns the empty string when the driver name is unknown', function () use ($llmConfig) {
    $manager = new LlmManager($llmConfig('mystery'));

    expect($manager->modelName())->toBe('');
});

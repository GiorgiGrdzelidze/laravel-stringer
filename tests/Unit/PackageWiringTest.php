<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\BindingResolutionException;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\Contracts\ContextBuilder;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Llm\Drivers\GeminiClient;
use Stringer\Laravel\StringerServiceProvider;

it('registers the service provider', function () {
    expect(app()->getLoadedProviders())->toHaveKey(StringerServiceProvider::class);
});

it('merges the package config', function () {
    expect(config('stringer'))->toBeArray()
        ->and(config('stringer.llm.driver'))->toBe('gemini')
        ->and(config('stringer.locales.list'))->toBe(['en', 'ka', 'ru'])
        ->and(config('stringer.tables.blog_topics'))->toBe('blog_topics');
});

it('binds LlmClient to the configured driver', function () {
    expect(app(LlmClient::class))->toBeInstanceOf(GeminiClient::class);
});

it('does not bind host contracts — those are the host adapter\'s job', function () {
    expect(fn () => app(ContentTarget::class))->toThrow(BindingResolutionException::class);
    expect(fn () => app(ContextBuilder::class))->toThrow(BindingResolutionException::class);
});

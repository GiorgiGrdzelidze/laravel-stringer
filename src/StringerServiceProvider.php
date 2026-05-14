<?php

declare(strict_types=1);

namespace Stringer\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Llm\LlmManager;

final class StringerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/stringer.php', 'stringer');

        $this->app->singleton(LlmManager::class, function (Application $app): LlmManager {
            /** @var array{driver: string, api_keys: array<string, ?string>, models: array<string, string>} $config */
            $config = $app['config']->get('stringer.llm', []);

            return new LlmManager($config);
        });

        $this->app->bind(LlmClient::class, fn (Application $app): LlmClient => $app->make(LlmManager::class)->make());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/stringer.php' => config_path('stringer.php'),
            ], 'stringer-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'stringer-migrations');
        }
    }
}

<?php

declare(strict_types=1);

namespace Stringer\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Database\Seeders\StringerDefaultContentFieldsSeeder;
use Stringer\Laravel\Database\Seeders\StringerDefaultPromptsSeeder;
use Stringer\Laravel\Http\Controllers\TelegramWebhookController;
use Stringer\Laravel\Http\Middleware\VerifyTelegramSecret;
use Stringer\Laravel\Llm\LlmManager;
use Stringer\Laravel\Prompts\DbPromptBuilder;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\TelegramClient;
use Throwable;

final class StringerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/stringer.php', 'stringer');

        $this->app->singleton(LlmManager::class, function (Application $app): LlmManager {
            return new LlmManager($app['config']);
        });

        $this->app->bind(LlmClient::class, fn (Application $app): LlmClient => $app->make(LlmManager::class)->make());

        $this->app->singleton(TopicQueue::class);

        $this->app->bind(PromptBuilder::class, DbPromptBuilder::class);

        $this->app->singleton(TelegramClient::class, function (): TelegramClient {
            return new TelegramClient((string) config('stringer.telegram.bot_token', ''));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerTelegramWebhookRoute();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/stringer.php' => config_path('stringer.php'),
            ], 'stringer-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'stringer-migrations');

            $this->autoSeedDefaults();
        }
    }

    private function registerTelegramWebhookRoute(): void
    {
        if (! (bool) config('stringer.telegram.enabled', true)) {
            return;
        }

        Route::post('webhooks/telegram/{secret}', TelegramWebhookController::class)
            ->where('secret', '[A-Za-z0-9_-]{16,}')
            ->middleware(VerifyTelegramSecret::class)
            ->name('stringer.telegram.webhook');
    }

    /**
     * Idempotent best-effort seeding for the two DB-managed config tables.
     * Each seeder no-ops if its table already has rows, so this is safe to
     * run on every console boot.
     */
    private function autoSeedDefaults(): void
    {
        // Tests own their own fixture state — never auto-seed during testing.
        if ($this->app->environment('testing')) {
            return;
        }

        if (! (bool) config('stringer.seed_defaults_on_boot', true)) {
            return;
        }

        try {
            $promptsTable = (string) config('stringer.tables.stringer_prompts', 'stringer_prompts');
            if (Schema::hasTable($promptsTable)) {
                (new StringerDefaultPromptsSeeder)->run();
            }

            $fieldsTable = (string) config('stringer.tables.stringer_content_fields', 'stringer_content_fields');
            if (Schema::hasTable($fieldsTable)) {
                (new StringerDefaultContentFieldsSeeder)->run();
            }
        } catch (Throwable) {
            // Never break boot on a seeder failure — operator can run the
            // seeders manually via `php artisan db:seed --class=...`.
        }
    }
}

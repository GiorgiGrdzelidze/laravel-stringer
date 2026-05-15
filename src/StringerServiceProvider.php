<?php

declare(strict_types=1);

namespace Stringer\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Stringer\Laravel\Console\Commands\BackfillImagesCommand;
use Stringer\Laravel\Console\Commands\RegisterTelegramCommandsCommand;
use Stringer\Laravel\Contracts\ImageGenerator;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Database\Seeders\StringerDefaultContentFieldsSeeder;
use Stringer\Laravel\Database\Seeders\StringerDefaultPromptsSeeder;
use Stringer\Laravel\Http\Controllers\TelegramWebhookController;
use Stringer\Laravel\Http\Middleware\VerifyTelegramSecret;
use Stringer\Laravel\Images\ImageManager;
use Stringer\Laravel\Jobs\AutoGenerateWeeklyJob;
use Stringer\Laravel\Llm\LlmManager;
use Stringer\Laravel\Prompts\DbPromptBuilder;
use Stringer\Laravel\Services\TopicQueue;
use Stringer\Laravel\Telegram\Menu\Contracts\CategoryDirectory;
use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuTranslator;
use Stringer\Laravel\Telegram\Menu\EloquentChatStateStore;
use Stringer\Laravel\Telegram\Menu\MenuRenderer;
use Stringer\Laravel\Telegram\Menu\MenuRouter;
use Stringer\Laravel\Telegram\Menu\NullCategoryDirectory;
use Stringer\Laravel\Telegram\Menu\PendingInputResolver;
use Stringer\Laravel\Telegram\Menu\PendingInputStore;
use Stringer\Laravel\Telegram\Menu\Stringer\CategoriesNode;
use Stringer\Laravel\Telegram\Menu\Stringer\GenerateNode;
use Stringer\Laravel\Telegram\Menu\Stringer\RootNode;
use Stringer\Laravel\Telegram\Menu\Stringer\StringerDictionary;
use Stringer\Laravel\Telegram\Menu\Stringer\TopicsNode;
use Stringer\Laravel\Telegram\Menu\Translation\ArrayTranslator;
use Stringer\Laravel\Telegram\Menu\Translation\BuiltinDictionary;
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

        $this->app->singleton(ImageManager::class, function (Application $app): ImageManager {
            return new ImageManager($app['config']);
        });

        $this->app->bind(
            ImageGenerator::class,
            fn (Application $app): ImageGenerator => $app->make(ImageManager::class)->make(),
        );

        $this->app->singleton(TopicQueue::class);

        $this->app->bind(PromptBuilder::class, DbPromptBuilder::class);

        $this->app->singleton(TelegramClient::class, function (): TelegramClient {
            return new TelegramClient((string) config('stringer.telegram.bot_token', ''));
        });

        $this->registerMenuBindings();
    }

    /**
     * Wire the Telegram menu services. Hosts can override individual bindings
     * (e.g. swap `EloquentChatStateStore` for a cache-backed store) in their
     * own ServiceProvider — these are registered with `bind`, not
     * `singleton`-by-class, so they're overridable.
     */
    private function registerMenuBindings(): void
    {
        $this->app->singleton(ChatStateStore::class, EloquentChatStateStore::class);
        $this->app->singleton(PendingInputStore::class);
        $this->app->bind(CategoryDirectory::class, NullCategoryDirectory::class);

        $this->app->singleton(MenuTranslator::class, function (): MenuTranslator {
            return new ArrayTranslator(
                array_replace_recursive(BuiltinDictionary::all(), StringerDictionary::all()),
                fallbackLocale: (string) config('app.fallback_locale', 'en'),
            );
        });

        $this->app->singleton(MenuRenderer::class);

        $this->app->singleton(MenuRouter::class, function (Application $app): MenuRouter {
            return new MenuRouter(
                state: $app->make(ChatStateStore::class),
                renderer: $app->make(MenuRenderer::class),
                translator: $app->make(MenuTranslator::class),
                rootResolver: fn (): RootNode => $app->make(RootNode::class),
                defaultLocale: (string) config('app.locale', 'en'),
            );
        });

        $this->app->singleton(PendingInputResolver::class, function (Application $app): PendingInputResolver {
            return new PendingInputResolver(
                pendingInputs: $app->make(PendingInputStore::class),
                state: $app->make(ChatStateStore::class),
                telegram: $app->make(TelegramClient::class),
                translator: $app->make(MenuTranslator::class),
                defaultLocale: (string) config('app.locale', 'en'),
            );
        });

        // Stringer-specific node graph — host swaps these for their own
        // implementations in projects that aren't Stringer.
        $this->app->singleton(TopicsNode::class);
        $this->app->singleton(GenerateNode::class);
        $this->app->singleton(CategoriesNode::class);
        $this->app->singleton(RootNode::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'stringer');

        $this->registerTelegramWebhookRoute();
        $this->registerScheduledJobs();

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterTelegramCommandsCommand::class,
                BackfillImagesCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/stringer.php' => config_path('stringer.php'),
            ], 'stringer-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'stringer-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/stringer'),
            ], 'stringer-views');

            $this->autoSeedDefaults();
        }
    }

    /**
     * Schedule the weekly auto-generate job. Cron + timezone come from
     * config (env-overridable). The callAfterResolving hook waits until
     * the Schedule singleton is built, which happens lazily in console
     * mode — so this is a no-op for HTTP requests.
     */
    private function registerScheduledJobs(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $cron = (string) config('stringer.schedule.auto_generate_cron', '0 9 * * 1');
            $timezone = (string) config('stringer.schedule.auto_generate_timezone', 'Asia/Tbilisi');

            $schedule->job(new AutoGenerateWeeklyJob)
                ->cron($cron)
                ->timezone($timezone)
                ->name('stringer.auto_generate_weekly');
        });
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

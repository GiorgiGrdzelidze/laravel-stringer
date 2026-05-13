<?php

declare(strict_types=1);

namespace Stringer\Laravel;

use Illuminate\Support\ServiceProvider;

final class StringerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/stringer.php', 'stringer');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/stringer.php' => config_path('stringer.php'),
            ], 'stringer-config');
        }
    }
}

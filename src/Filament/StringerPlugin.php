<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Stringer\Laravel\Filament\Pages\ManageStringerSettings;
use Stringer\Laravel\Filament\Resources\BlogTopicResource\BlogTopicResource;
use Stringer\Laravel\Filament\Resources\StringerContentFieldResource\StringerContentFieldResource;
use Stringer\Laravel\Filament\Resources\StringerPromptResource\StringerPromptResource;

/**
 * Filament v5 plugin that registers Stringer's three Resources and the
 * ManageStringerSettings page on a host panel.
 *
 * Hosts opt in from their panel provider:
 *
 *     use Stringer\Laravel\Filament\StringerPlugin;
 *
 *     public function panel(Panel $panel): Panel
 *     {
 *         return $panel
 *             ->plugin(StringerPlugin::make())
 *             ...;
 *     }
 *
 * Registration happens at panel-`register()` time, which is the only
 * point in the v5 lifecycle when adding resources / pages is honored
 * (routes are built immediately after register and never re-scanned).
 */
final class StringerPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'stringer';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            BlogTopicResource::class,
            StringerPromptResource::class,
            StringerContentFieldResource::class,
        ]);

        $panel->pages([
            ManageStringerSettings::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op for v0.1.0 — the resources/pages have their own routing
        // via Filament's panel route registration.
    }
}

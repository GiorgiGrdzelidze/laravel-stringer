<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerPromptResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Stringer\Laravel\Filament\Resources\StringerPromptResource\StringerPromptResource;

final class ListStringerPrompts extends ListRecords
{
    protected static string $resource = StringerPromptResource::class;

    /**
     * No header actions — prompts are seeded by the package and edited in
     * place. `canCreate()` returns false on the resource, so any "New"
     * button here would just 403.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}

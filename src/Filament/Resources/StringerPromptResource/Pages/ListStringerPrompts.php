<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerPromptResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Stringer\Laravel\Filament\Resources\StringerPromptResource\StringerPromptResource;

final class ListStringerPrompts extends ListRecords
{
    protected static string $resource = StringerPromptResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

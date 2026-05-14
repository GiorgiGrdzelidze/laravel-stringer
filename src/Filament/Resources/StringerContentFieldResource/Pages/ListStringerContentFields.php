<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerContentFieldResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Stringer\Laravel\Filament\Resources\StringerContentFieldResource\StringerContentFieldResource;

final class ListStringerContentFields extends ListRecords
{
    protected static string $resource = StringerContentFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

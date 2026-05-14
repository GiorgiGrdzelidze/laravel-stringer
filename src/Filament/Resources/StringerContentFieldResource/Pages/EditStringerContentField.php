<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerContentFieldResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Stringer\Laravel\Filament\Resources\StringerContentFieldResource\StringerContentFieldResource;

final class EditStringerContentField extends EditRecord
{
    protected static string $resource = StringerContentFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

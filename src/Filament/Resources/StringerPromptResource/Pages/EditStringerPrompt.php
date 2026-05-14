<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerPromptResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Stringer\Laravel\Filament\Resources\StringerPromptResource\StringerPromptResource;

final class EditStringerPrompt extends EditRecord
{
    protected static string $resource = StringerPromptResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

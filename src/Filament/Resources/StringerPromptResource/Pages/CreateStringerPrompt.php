<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerPromptResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Stringer\Laravel\Filament\Resources\StringerPromptResource\StringerPromptResource;

final class CreateStringerPrompt extends CreateRecord
{
    protected static string $resource = StringerPromptResource::class;
}

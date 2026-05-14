<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerContentFieldResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Stringer\Laravel\Filament\Resources\StringerContentFieldResource\StringerContentFieldResource;

final class CreateStringerContentField extends CreateRecord
{
    protected static string $resource = StringerContentFieldResource::class;
}

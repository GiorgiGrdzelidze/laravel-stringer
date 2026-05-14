<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\BlogTopicResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Stringer\Laravel\Filament\Resources\BlogTopicResource\BlogTopicResource;

final class EditBlogTopic extends EditRecord
{
    protected static string $resource = BlogTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

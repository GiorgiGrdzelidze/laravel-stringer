<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\BlogTopicResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Stringer\Laravel\Filament\Resources\BlogTopicResource\BlogTopicResource;

final class ListBlogTopics extends ListRecords
{
    protected static string $resource = BlogTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

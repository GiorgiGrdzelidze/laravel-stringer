<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\BlogTopicResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Stringer\Laravel\Filament\Resources\BlogTopicResource\BlogTopicResource;

final class CreateBlogTopic extends CreateRecord
{
    protected static string $resource = BlogTopicResource::class;
}

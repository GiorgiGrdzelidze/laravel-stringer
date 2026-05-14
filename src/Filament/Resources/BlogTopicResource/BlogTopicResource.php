<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\BlogTopicResource;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Filament\Resources\BlogTopicResource\Pages\CreateBlogTopic;
use Stringer\Laravel\Filament\Resources\BlogTopicResource\Pages\EditBlogTopic;
use Stringer\Laravel\Filament\Resources\BlogTopicResource\Pages\ListBlogTopics;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\TopicQueue;

final class BlogTopicResource extends Resource
{
    protected static ?string $model = BlogTopic::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Stringer';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('hint')->required()->rows(3)->columnSpanFull(),
            Select::make('status')
                ->options(collect(TopicStatus::cases())->mapWithKeys(fn (TopicStatus $s) => [$s->value => $s->value])->all())
                ->required(),
            Select::make('source')
                ->options(collect(TopicSource::cases())->mapWithKeys(fn (TopicSource $s) => [$s->value => $s->value])->all())
                ->required()
                ->disabledOn('edit'),
            Toggle::make('auto_publish')->helperText('When on, ArticleTarget writes with publish_at=now() and the chosen target_status.'),
            Select::make('target_status')
                ->options(['draft' => 'draft', 'published' => 'published', 'scheduled' => 'scheduled'])
                ->default('draft'),
            Textarea::make('last_error')->disabled()->rows(3)->columnSpanFull(),
            TextInput::make('article_id')->numeric()->disabled()->label('Article ID'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('source')->badge(),
                IconColumn::make('auto_publish')->boolean(),
                TextColumn::make('hint')->limit(60),
                TextColumn::make('created_at')->since()->sortable(),
                TextColumn::make('drafted_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(TopicStatus::cases())->mapWithKeys(fn (TopicStatus $s) => [$s->value => $s->value])->all()),
                SelectFilter::make('source')->options(collect(TopicSource::cases())->mapWithKeys(fn (TopicSource $s) => [$s->value => $s->value])->all()),
                TernaryFilter::make('auto_publish'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('generateNow')
                    ->label('Generate Now')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->action(fn (BlogTopic $record) => GenerateDraftJob::dispatch($record->id)),
                Action::make('spike')
                    ->label('Spike')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->action(fn (BlogTopic $record) => app(TopicQueue::class)->markRejected($record)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogTopics::route('/'),
            'create' => CreateBlogTopic::route('/create'),
            'edit' => EditBlogTopic::route('/{record}/edit'),
        ];
    }
}

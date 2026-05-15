<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerPromptResource;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
use Stringer\Laravel\Filament\Resources\StringerPromptResource\Pages\EditStringerPrompt;
use Stringer\Laravel\Filament\Resources\StringerPromptResource\Pages\ListStringerPrompts;
use Stringer\Laravel\Models\StringerPrompt;

final class StringerPromptResource extends Resource
{
    protected static ?string $model = StringerPrompt::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Stringer';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->required()->datalist(['draft', 'translate', 'cover_image']),
            Select::make('locale')
                ->options(['en' => 'en', 'ka' => 'ka', 'ru' => 'ru'])
                ->placeholder('null (all locales)')
                ->nullable(),
            TextInput::make('description')->columnSpanFull(),
            Textarea::make('content')->required()->rows(20)->columnSpanFull()->autosize(),
            TagsInput::make('variables')->columnSpanFull()->helperText('Placeholder names available in this template ({{voice}}, {{hint}}, …).'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable(),
                TextColumn::make('locale')->placeholder('—'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('locale')->options(['en' => 'en', 'ka' => 'ka', 'ru' => 'ru']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (StringerPrompt $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->action(fn (StringerPrompt $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->defaultSort('key');
    }

    // Prompt rows are seeded by the package — there is exactly one canonical
    // template per `(key, locale)` pair (`draft`, `translate`, `cover_image`).
    // Operators only need to *edit* the seeded rows; creating new rows or
    // duplicating existing ones is a footgun (the lookup picks `LIMIT 1`,
    // so a stale duplicate can silently win over the operator's edits).
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStringerPrompts::route('/'),
            'edit' => EditStringerPrompt::route('/{record}/edit'),
        ];
    }
}

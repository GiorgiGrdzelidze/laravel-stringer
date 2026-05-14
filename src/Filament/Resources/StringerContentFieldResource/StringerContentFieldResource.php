<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Resources\StringerContentFieldResource;

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
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Filament\Resources\StringerContentFieldResource\Pages\CreateStringerContentField;
use Stringer\Laravel\Filament\Resources\StringerContentFieldResource\Pages\EditStringerContentField;
use Stringer\Laravel\Filament\Resources\StringerContentFieldResource\Pages\ListStringerContentFields;
use Stringer\Laravel\Models\StringerContentField;

final class StringerContentFieldResource extends Resource
{
    protected static ?string $model = StringerContentField::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Stringer';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->disabledOn('edit'),
            Select::make('type')
                ->options(collect(FieldType::cases())->mapWithKeys(fn (FieldType $t) => [$t->value => $t->value])->all())
                ->required()
                ->live(),
            TagsInput::make('locales')
                ->placeholder('en, ka, ru')
                ->visible(fn ($get) => in_array($get('type'), [FieldType::TranslatableString->value, FieldType::TranslatableMarkdown->value], true)),
            TextInput::make('max_length')->numeric()
                ->visible(fn ($get) => in_array($get('type'), [FieldType::String->value, FieldType::TranslatableString->value], true)),
            TextInput::make('max_words')->numeric()
                ->visible(fn ($get) => in_array($get('type'), [FieldType::Markdown->value, FieldType::TranslatableMarkdown->value], true)),
            TextInput::make('min')->numeric()
                ->visible(fn ($get) => $get('type') === FieldType::TagList->value),
            TextInput::make('max')->numeric()
                ->visible(fn ($get) => $get('type') === FieldType::TagList->value),
            Textarea::make('instruction')->required()->rows(4)->columnSpanFull(),
            Toggle::make('is_required')->default(true),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                IconColumn::make('is_active')->boolean(),
                IconColumn::make('is_required')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('type')->options(collect(FieldType::cases())->mapWithKeys(fn (FieldType $t) => [$t->value => $t->value])->all()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (StringerContentField $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->action(fn (StringerContentField $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStringerContentFields::route('/'),
            'create' => CreateStringerContentField::route('/create'),
            'edit' => EditStringerContentField::route('/{record}/edit'),
        ];
    }
}

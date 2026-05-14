<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Stringer\Laravel\Models\StringerSetting;

/**
 * Single-form settings page for Stringer.
 *
 * Persists to the single-row `stringer_settings` table. v0.1.0 is
 * write-only from the package's perspective — consumer code still
 * reads env/config. Wiring runtime overlay over the env baseline is
 * v0.2 territory; the page exists so the operator-edited fields have
 * somewhere to live.
 */
final class ManageStringerSettings extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Stringer';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament-panels::pages.simple-form';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $setting = StringerSetting::current();

        $this->data = [
            'voice_card' => $setting->voice_card,
            'body_word_cap' => $setting->body_word_cap,
            'tag_count' => $setting->tag_count,
            'auto_generate_cron' => $setting->auto_generate_cron,
            'auto_generate_timezone' => $setting->auto_generate_timezone,
            'allowed_chat_ids' => $setting->allowed_chat_ids ?? [],
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('voice_card')->rows(4)->columnSpanFull()
                    ->placeholder('first-person, dry, technical confidence, occasional dry humor, no marketing fluff'),
                TextInput::make('body_word_cap')->numeric()->placeholder('800'),
                TextInput::make('tag_count')->numeric()->placeholder('5'),
                TextInput::make('auto_generate_cron')->placeholder('0 9 * * 1'),
                Select::make('auto_generate_timezone')
                    ->options(['Asia/Tbilisi' => 'Asia/Tbilisi', 'UTC' => 'UTC', 'Europe/London' => 'Europe/London'])
                    ->placeholder('Asia/Tbilisi'),
                TagsInput::make('allowed_chat_ids')->placeholder('add a chat id and press enter')->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }

    public function save(): void
    {
        $setting = StringerSetting::current();
        $setting->fill([
            'voice_card' => $this->data['voice_card'] ?? null,
            'body_word_cap' => $this->data['body_word_cap'] ?? null,
            'tag_count' => $this->data['tag_count'] ?? null,
            'auto_generate_cron' => $this->data['auto_generate_cron'] ?? null,
            'auto_generate_timezone' => $this->data['auto_generate_timezone'] ?? null,
            'allowed_chat_ids' => array_values(array_map('intval', $this->data['allowed_chat_ids'] ?? [])),
        ])->save();

        $this->dispatch('notify', message: 'Settings saved.');
    }
}

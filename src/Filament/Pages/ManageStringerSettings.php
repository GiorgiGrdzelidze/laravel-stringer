<?php

declare(strict_types=1);

namespace Stringer\Laravel\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Stringer\Laravel\Models\StringerSetting;

/**
 * Single-form settings page for Stringer.
 *
 * Persists to the single-row `stringer_settings` table via the
 * `StringerSetting::current()` singleton. v0.1.0 is write-only from
 * the package's perspective — consumer code still reads env/config.
 * Runtime overlay over the env baseline is v0.2 territory; the page
 * exists so the operator-edited fields have somewhere to live.
 *
 * @property Schema $form Magic property exposed by InteractsWithForms.
 */
final class ManageStringerSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'Stringer';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'manage-stringer-settings';

    protected string $view = 'stringer::pages.manage-stringer-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $setting = StringerSetting::current();

        $this->form->fill([
            'voice_card' => $setting->voice_card,
            'body_word_cap' => $setting->body_word_cap,
            'tag_count' => $setting->tag_count,
            'auto_generate_cron' => $setting->auto_generate_cron,
            'auto_generate_timezone' => $setting->auto_generate_timezone,
            'allowed_chat_ids' => $setting->allowed_chat_ids ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('voice_card')
                    ->rows(4)
                    ->columnSpanFull()
                    ->placeholder('first-person, dry, technical confidence, occasional dry humor, no marketing fluff'),
                TextInput::make('body_word_cap')->numeric()->required()->placeholder('800'),
                TextInput::make('tag_count')->numeric()->required()->placeholder('5'),
                TextInput::make('auto_generate_cron')->required()->placeholder('0 9 * * 1'),
                Select::make('auto_generate_timezone')
                    ->options(['Asia/Tbilisi' => 'Asia/Tbilisi', 'UTC' => 'UTC', 'Europe/London' => 'Europe/London'])
                    ->required()
                    ->placeholder('Asia/Tbilisi'),
                TagsInput::make('allowed_chat_ids')
                    ->placeholder('add a chat id and press enter')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();

        StringerSetting::current()->fill([
            'voice_card' => $data['voice_card'] ?? null,
            'body_word_cap' => $data['body_word_cap'] ?? null,
            'tag_count' => $data['tag_count'] ?? null,
            'auto_generate_cron' => $data['auto_generate_cron'] ?? null,
            'auto_generate_timezone' => $data['auto_generate_timezone'] ?? null,
            'allowed_chat_ids' => array_values(array_map('intval', $data['allowed_chat_ids'] ?? [])),
        ])->save();

        Notification::make()
            ->title('Settings saved.')
            ->success()
            ->send();
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Save')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }
}

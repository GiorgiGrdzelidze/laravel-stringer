<?php

declare(strict_types=1);

namespace Stringer\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuTranslator;
use Stringer\Laravel\Telegram\TelegramClient;
use Throwable;

/**
 * Registers Stringer's command list with Telegram via the bot API's
 * `setMyCommands` method.
 *
 * Run once after `composer require` (and after the env vars are configured).
 * Re-run safely after the command set changes — Telegram replaces the whole
 * list per call. The bot's "menu" button (blue square next to the chat
 * input) will show the registered commands in every chat with the bot.
 *
 * Per-locale descriptions: Telegram picks the right `language_code` set
 * based on each user's app locale. We push all three locales we support
 * (en/ka/ru) plus a default fallback set.
 */
final class RegisterTelegramCommandsCommand extends Command
{
    protected $signature = 'stringer:telegram:register-commands';

    protected $description = 'Register the bot command list with Telegram (blue menu button next to chat input)';

    /**
     * @return list<array{command: string, key: string}>
     */
    private const COMMANDS = [
        ['command' => 'start', 'key' => 'commands.start'],
        ['command' => 'generate', 'key' => 'commands.generate'],
        ['command' => 'list', 'key' => 'commands.list'],
        ['command' => 'spike', 'key' => 'commands.spike'],
        ['command' => 'help', 'key' => 'commands.help'],
    ];

    public function handle(TelegramClient $telegram, MenuTranslator $translator): int
    {
        $locales = (array) config('telegram-menu.locales', ['en', 'ka', 'ru']);
        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        // Default set (no language_code) — used when the user's app locale
        // isn't in our list.
        $this->push($telegram, $translator, $fallbackLocale, null);

        foreach ($locales as $locale) {
            if (! is_string($locale)) {
                continue;
            }
            $this->push($telegram, $translator, $locale, $locale);
        }

        $this->info('Telegram command menu registered.');

        return self::SUCCESS;
    }

    private function push(TelegramClient $telegram, MenuTranslator $translator, string $locale, ?string $languageCode): void
    {
        $commands = [];
        foreach (self::COMMANDS as $entry) {
            $commands[] = [
                'command' => $entry['command'],
                'description' => $translator->translate($entry['key'], $locale),
            ];
        }

        try {
            $telegram->setMyCommands($commands, $languageCode);
            $label = $languageCode === null ? 'default' : $languageCode;
            $this->line("  · pushed {$label} ({$locale})");
        } catch (Throwable $e) {
            $this->error("  · failed for locale {$locale}: ".$e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Translation;

/**
 * Translation strings the menu mechanics need regardless of the host.
 *
 * Universal across every project that uses this menu — back button, language
 * picker, generic errors. Host-specific labels (e.g. Stringer's
 * `stringer.menu.root.generate`) live in their own dictionary and are merged
 * in on top of these via `ArrayTranslator::extend()`.
 */
final class BuiltinDictionary
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return [
            'en' => [
                'menu.back' => '← Back',
                'menu.cancel' => '✖ Cancel',
                'menu.yes' => '✅ Yes',
                'menu.no' => '✖ No',
                'menu.dismissed' => '👋',
                'menu.error.generic' => 'Something went wrong. Try /start to restart the menu.',
                'menu.error.stale_path' => 'The menu has been updated. Send /start to refresh.',
                'menu.pending.expired' => 'You waited a bit too long — that prompt expired. Tap the button again.',
                'lang.title' => 'Pick a language:',
                'lang.button' => '🌐 Language',
                'lang.saved' => 'Language set to :language.',
                'lang.name.en' => '🇬🇧 English',
                'lang.name.ka' => '🇬🇪 ქართული',
                'lang.name.ru' => '🇷🇺 Русский',
                'commands.start' => 'Open the main menu',
                'commands.generate' => 'Enqueue a topic or generate a draft',
                'commands.list' => 'Show recent topics',
                'commands.spike' => 'Reject a topic',
                'commands.help' => 'Help & commands',
            ],
            'ka' => [
                'menu.back' => '← უკან',
                'menu.cancel' => '✖ გაუქმება',
                'menu.yes' => '✅ კი',
                'menu.no' => '✖ არა',
                'menu.dismissed' => '👋',
                'menu.error.generic' => 'რაღაც შეცდომა მოხდა. სცადე /start მენიუს გადასახარდენად.',
                'menu.error.stale_path' => 'მენიუ განახლდა. გაგზავნე /start განახლებისთვის.',
                'menu.pending.expired' => 'ცოტა გვიან გამოეხმაურე — ეგ მოთხოვნა ვადაგასულია. დააჭირე ღილაკს ხელახლა.',
                'lang.title' => 'აირჩიე ენა:',
                'lang.button' => '🌐 ენა',
                'lang.saved' => 'ენა შეცვლილია: :language.',
                'lang.name.en' => '🇬🇧 English',
                'lang.name.ka' => '🇬🇪 ქართული',
                'lang.name.ru' => '🇷🇺 Русский',
                'commands.start' => 'მთავარი მენიუ',
                'commands.generate' => 'თემის რიგში დამატება ან გენერაცია',
                'commands.list' => 'ბოლო თემები',
                'commands.spike' => 'თემის უარყოფა',
                'commands.help' => 'დახმარება და ბრძანებები',
            ],
            'ru' => [
                'menu.back' => '← Назад',
                'menu.cancel' => '✖ Отмена',
                'menu.yes' => '✅ Да',
                'menu.no' => '✖ Нет',
                'menu.dismissed' => '👋',
                'menu.error.generic' => 'Что-то пошло не так. Отправь /start, чтобы перезапустить меню.',
                'menu.error.stale_path' => 'Меню обновилось. Отправь /start для обновления.',
                'menu.pending.expired' => 'Немного задержался — этот запрос истёк. Нажми на кнопку ещё раз.',
                'lang.title' => 'Выбери язык:',
                'lang.button' => '🌐 Язык',
                'lang.saved' => 'Язык установлен: :language.',
                'lang.name.en' => '🇬🇧 English',
                'lang.name.ka' => '🇬🇪 ქართული',
                'lang.name.ru' => '🇷🇺 Русский',
                'commands.start' => 'Главное меню',
                'commands.generate' => 'Добавить тему или сгенерировать черновик',
                'commands.list' => 'Последние темы',
                'commands.spike' => 'Отклонить тему',
                'commands.help' => 'Помощь и команды',
            ],
        ];
    }
}

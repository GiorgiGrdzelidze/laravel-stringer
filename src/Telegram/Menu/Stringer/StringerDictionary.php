<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\Stringer;

/**
 * Translation strings for Stringer's menu screens — distinct from the
 * universal `BuiltinDictionary` so the menu mechanics stay reusable across
 * projects. Other hosts (e.g. ExploreGeorgia) ship their own dictionary in
 * the same shape.
 */
final class StringerDictionary
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return [
            'en' => [
                'stringer.root.title' => "Welcome to Stringer 👋\nPick what you want to do:",
                'stringer.root.generate' => '✍ Generate',
                'stringer.root.topics' => '📋 Topics',
                'stringer.root.categories' => '📚 Categories',
                'stringer.root.settings' => '⚙ Settings',
                'stringer.root.help' => '❓ Help',
                'stringer.generate.title' => 'How would you like to generate?',
                'stringer.generate.hint' => '📝 By topic hint',
                'stringer.generate.category' => '📚 By category',
                'stringer.generate.auto' => '🎲 Auto pick for me',
                'stringer.generate.hint_prompt' => 'Send your topic hint as the next message — I have 60 seconds.',
                'stringer.generate.enqueued' => 'Topic #:id added to the queue. Generation dispatched.',
                'stringer.categories.title' => 'Pick a category:',
                'stringer.categories.empty' => 'No categories configured yet. Set them up in the admin panel.',
                'stringer.category.title' => 'What in :category?',
                'stringer.category.fire' => '✅ Generate fresh post',
                'stringer.category.with_hint' => '📝 With a hint first',
                'stringer.topics.title' => 'Recent topics:',
                'stringer.topics.empty' => 'No topics yet. Tap Generate to enqueue one.',
                'stringer.topics.entry' => '#:id · :status · :hint',
                'stringer.settings.title' => '⚙ Settings',
                'stringer.help.title' => 'Stringer help',
                'stringer.help.body' => "Commands you can also type directly:\n• /generate <hint> — enqueue a topic\n• /generate <id> — force-draft a topic\n• /list — show recent topics\n• /spike <id> — reject a topic\n• /help — this menu\n\nOr just tap your way through the menu — same outcome.",
            ],
            'ka' => [
                'stringer.root.title' => "Stringer-ში მოგესალმებით 👋\nაირჩიე რა გინდა გააკეთო:",
                'stringer.root.generate' => '✍ შექმნა',
                'stringer.root.topics' => '📋 თემები',
                'stringer.root.categories' => '📚 კატეგორიები',
                'stringer.root.settings' => '⚙ პარამეტრები',
                'stringer.root.help' => '❓ დახმარება',
                'stringer.generate.title' => 'როგორ შევქმნათ?',
                'stringer.generate.hint' => '📝 თემის მინიშნებით',
                'stringer.generate.category' => '📚 კატეგორიის მიხედვით',
                'stringer.generate.auto' => '🎲 ავტომატური არჩევანი',
                'stringer.generate.hint_prompt' => 'გამოგზავნე თემის მინიშნება შემდეგ შეტყობინებაში — გაქვს 60 წამი.',
                'stringer.generate.enqueued' => 'თემა #:id რიგში დაემატა. გენერაცია დაიწყო.',
                'stringer.categories.title' => 'აირჩიე კატეგორია:',
                'stringer.categories.empty' => 'კატეგორიები არ არის. დააყენე ისინი ადმინ პანელში.',
                'stringer.category.title' => ':category — რა გავაკეთოთ?',
                'stringer.category.fire' => '✅ ახალი პოსტის შექმნა',
                'stringer.category.with_hint' => '📝 ჯერ მინიშნებით',
                'stringer.topics.title' => 'ბოლო თემები:',
                'stringer.topics.empty' => 'თემები ჯერ არ არის. დააჭირე „შექმნა"-ს რომ დაამატო.',
                'stringer.topics.entry' => '#:id · :status · :hint',
                'stringer.settings.title' => '⚙ პარამეტრები',
                'stringer.help.title' => 'Stringer-ის დახმარება',
                'stringer.help.body' => "ბრძანებები, რომელთა აკრეფაც პირდაპირაც შეგიძლია:\n• /generate <ჰინტი> — თემის რიგში დამატება\n• /generate <id> — თემის გენერაცია\n• /list — ბოლო თემები\n• /spike <id> — თემის უარყოფა\n• /help — ეს მენიუ\n\nან უბრალოდ გადახედე მენიუს ღილაკებით — შედეგი იგივეა.",
            ],
            'ru' => [
                'stringer.root.title' => "Добро пожаловать в Stringer 👋\nВыбери, что хочешь сделать:",
                'stringer.root.generate' => '✍ Создать',
                'stringer.root.topics' => '📋 Темы',
                'stringer.root.categories' => '📚 Категории',
                'stringer.root.settings' => '⚙ Настройки',
                'stringer.root.help' => '❓ Помощь',
                'stringer.generate.title' => 'Как будем создавать?',
                'stringer.generate.hint' => '📝 По подсказке темы',
                'stringer.generate.category' => '📚 По категории',
                'stringer.generate.auto' => '🎲 Автовыбор',
                'stringer.generate.hint_prompt' => 'Отправь подсказку темы следующим сообщением — у тебя 60 секунд.',
                'stringer.generate.enqueued' => 'Тема #:id добавлена в очередь. Генерация запущена.',
                'stringer.categories.title' => 'Выбери категорию:',
                'stringer.categories.empty' => 'Категории ещё не настроены. Настрой их в админ-панели.',
                'stringer.category.title' => 'Что в :category?',
                'stringer.category.fire' => '✅ Создать новый пост',
                'stringer.category.with_hint' => '📝 Сначала с подсказкой',
                'stringer.topics.title' => 'Последние темы:',
                'stringer.topics.empty' => 'Тем пока нет. Нажми Создать, чтобы добавить.',
                'stringer.topics.entry' => '#:id · :status · :hint',
                'stringer.settings.title' => '⚙ Настройки',
                'stringer.help.title' => 'Помощь по Stringer',
                'stringer.help.body' => "Команды, которые также можно набирать напрямую:\n• /generate <подсказка> — добавить тему в очередь\n• /generate <id> — запустить генерацию темы\n• /list — последние темы\n• /spike <id> — отклонить тему\n• /help — это меню\n\nИли просто нажимай кнопки в меню — результат тот же.",
            ],
        ];
    }
}

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\Models\TelegramChatState;
use Stringer\Laravel\Models\TelegramPendingInput;

uses(RefreshDatabase::class);

const MENU_WEBHOOK_SECRET = 'menu-test-secret-1234567890';
const MENU_CHAT_ID = 5151;

/**
 * @return array<string, mixed>
 */
function menuUpdate(string $text, int $chatId = MENU_CHAT_ID): array
{
    return [
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'text' => $text,
            'chat' => ['id' => $chatId, 'type' => 'private'],
        ],
    ];
}

beforeEach(function () {
    config()->set('stringer.telegram.enabled', true);
    config()->set('stringer.telegram.secret', MENU_WEBHOOK_SECRET);
    config()->set('stringer.telegram.bot_token', 'test-bot-token');
    config()->set('stringer.telegram.allowed_chat_ids', [MENU_CHAT_ID]);

    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
});

it('shows the root menu on /start and stores the chat state', function () {
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('/start'))->assertOk();

    Http::assertSent(function (Request $r): bool {
        if (! str_contains($r->url(), '/sendMessage')) {
            return false;
        }
        $body = (string) $r['text'];
        $keyboard = $r['reply_markup']['keyboard'] ?? null;

        return str_contains($body, 'Welcome to Stringer')
            && is_array($keyboard)
            && count($keyboard) >= 3;
    });

    expect(TelegramChatState::query()->find(MENU_CHAT_ID))->not->toBeNull()
        ->and(TelegramChatState::query()->find(MENU_CHAT_ID)->current_path)->toBe('root');
});

it('navigates from root to the Generate screen when the user taps Generate', function () {
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('/start'))->assertOk();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('✍ Generate'))->assertOk();

    Http::assertSent(function (Request $r): bool {
        if (! str_contains($r->url(), '/sendMessage')) {
            return false;
        }
        $body = (string) $r['text'];

        return str_contains($body, 'How would you like to generate?');
    });

    expect(TelegramChatState::query()->find(MENU_CHAT_ID)->current_path)->toBe('root.generate');
});

it('schedules a pending input when the user taps "By topic hint"', function () {
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('/start'))->assertOk();
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('✍ Generate'))->assertOk();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('📝 By topic hint'))->assertOk();

    expect(TelegramPendingInput::query()->find(MENU_CHAT_ID))->not->toBeNull()
        ->and(TelegramPendingInput::query()->find(MENU_CHAT_ID)->expected_action)
        ->toBe('stringer.generate.hint');
});

it('falls back to the legacy command dispatcher when the message does not match a menu button', function () {
    // No /start issued yet — chat is at default 'root' state. Sending /help
    // should NOT match any root button (root buttons are "✍ Generate" etc.),
    // so the legacy HelpCommand handler runs.
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('/help'))->assertOk();

    Http::assertSent(function (Request $r): bool {
        return str_contains((string) $r['text'], 'Commands:');
    });
});

it('walks back up the tree when the user taps the back button', function () {
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('/start'))->assertOk();
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('✍ Generate'))->assertOk();
    expect(TelegramChatState::query()->find(MENU_CHAT_ID)->current_path)->toBe('root.generate');

    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('← Back'))->assertOk();

    expect(TelegramChatState::query()->find(MENU_CHAT_ID)->current_path)->toBe('root');
});

it('persists language choice and renders the menu in the new locale', function () {
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('/start'))->assertOk();
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('⚙ Settings'))->assertOk();
    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('🌐 Language'))->assertOk();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

    $this->postJson('/webhooks/telegram/'.MENU_WEBHOOK_SECRET, menuUpdate('🇬🇪 ქართული'))->assertOk();

    expect(TelegramChatState::query()->find(MENU_CHAT_ID)->language)->toBe('ka');
});

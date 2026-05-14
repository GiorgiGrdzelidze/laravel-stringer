<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Jobs\GenerateDraftJob;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\TopicQueue;

uses(RefreshDatabase::class);

const WEBHOOK_SECRET = 'test-secret-1234567890';

const ALLOWED_CHAT = 4242;

/**
 * @return array<string, mixed>
 */
function updatePayload(string $text, int $chatId = ALLOWED_CHAT): array
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
    config()->set('stringer.telegram.secret', WEBHOOK_SECRET);
    config()->set('stringer.telegram.bot_token', 'test-bot-token');
    config()->set('stringer.telegram.allowed_chat_ids', [ALLOWED_CHAT]);

    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
});

it('returns 404 on a wrong webhook secret', function () {
    $this->postJson('/webhooks/telegram/wrong-secret-1234567890', updatePayload('/help'))
        ->assertStatus(404);

    Http::assertNothingSent();
});

it('returns 200 with empty body on a non-allowlisted chat (no Telegram retry)', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/help', 9999))
        ->assertStatus(200);

    Http::assertNothingSent();
});

it('returns 200 ok for an allowlisted /help command and replies via Telegram', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/help'))
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'api.telegram.org/bottest-bot-token/sendMessage')
            && $request['chat_id'] === ALLOWED_CHAT
            && str_contains((string) $request['text'], 'Commands:');
    });
});

it('enqueues a Manual topic for non-command free text and replies with the new id', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('what is laravel scout?'))
        ->assertOk();

    $topic = BlogTopic::query()->first();
    expect($topic)->not->toBeNull()
        ->and($topic->hint)->toBe('what is laravel scout?')
        ->and($topic->source)->toBe(TopicSource::Manual)
        ->and($topic->status)->toBe(TopicStatus::Queued)
        ->and($topic->requested_by_chat_id)->toBe(ALLOWED_CHAT);

    Http::assertSent(fn (Request $r) => str_contains((string) $r['text'], "#{$topic->id}"));
});

it('replies with pending topics for /generate with no argument', function () {
    (new TopicQueue)->enqueue('first', TopicSource::Manual);
    (new TopicQueue)->enqueue('second', TopicSource::Manual);

    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/generate'))->assertOk();

    Http::assertSent(function (Request $r) {
        $text = (string) $r['text'];

        return str_contains($text, 'Pending:') && str_contains($text, 'first') && str_contains($text, 'second');
    });
});

it('enqueues a Manual topic when /generate is given free-text', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/generate write about laravel queues'))
        ->assertOk();

    $topic = BlogTopic::query()->first();
    expect($topic?->hint)->toBe('write about laravel queues')
        ->and($topic?->source)->toBe(TopicSource::Manual);
});

it('dispatches GenerateDraftJob when /generate is given a numeric topic id', function () {
    Bus::fake();

    $topic = (new TopicQueue)->enqueue('existing', TopicSource::Manual);

    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload("/generate {$topic->id}"))
        ->assertOk();

    Bus::assertDispatched(GenerateDraftJob::class, fn (GenerateDraftJob $job) => $job->topicId === $topic->id);
});

it('replies "not found" when /generate is given an unknown id', function () {
    Bus::fake();

    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/generate 999'))->assertOk();

    Bus::assertNotDispatched(GenerateDraftJob::class);
    Http::assertSent(fn (Request $r) => str_contains((string) $r['text'], 'not found'));
});

it('marks the topic Rejected on /spike {id}', function () {
    $topic = (new TopicQueue)->enqueue('existing', TopicSource::Manual);

    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload("/spike {$topic->id}"))->assertOk();

    expect($topic->fresh()->status)->toBe(TopicStatus::Rejected);

    Http::assertSent(fn (Request $r) => str_contains((string) $r['text'], 'rejected'));
});

it('shows usage hint when /spike is missing an id', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/spike'))->assertOk();

    Http::assertSent(fn (Request $r) => str_contains((string) $r['text'], 'Usage: /spike'));
});

it('lists topics on /list', function () {
    (new TopicQueue)->enqueue('alpha', TopicSource::Manual);
    (new TopicQueue)->enqueue('beta', TopicSource::Manual);

    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/list'))->assertOk();

    Http::assertSent(function (Request $r) {
        $text = (string) $r['text'];

        return str_contains($text, 'Recent topics:') && str_contains($text, 'alpha') && str_contains($text, 'beta');
    });
});

it('replies "queue empty" on /list when no topics exist', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/list'))->assertOk();

    Http::assertSent(fn (Request $r) => str_contains((string) $r['text'], 'No topics yet'));
});

it('treats /draft as an alias for /generate', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/draft write about queues'))
        ->assertOk();

    expect(BlogTopic::first()?->hint)->toBe('write about queues');
});

it('falls back to free-text enqueue for unknown slash commands', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, updatePayload('/notarealcommand here is the body'))
        ->assertOk();

    $topic = BlogTopic::first();
    expect($topic?->hint)->toBe('here is the body')
        ->and($topic?->source)->toBe(TopicSource::Manual);
});

it('returns 200 on a malformed payload (no message field) without retrying', function () {
    $this->postJson('/webhooks/telegram/'.WEBHOOK_SECRET, ['update_id' => 1])
        ->assertOk();

    expect(BlogTopic::count())->toBe(0);
    Http::assertNothingSent();
});

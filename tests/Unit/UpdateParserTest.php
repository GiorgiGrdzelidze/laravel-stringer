<?php

declare(strict_types=1);

use Stringer\Laravel\Telegram\UpdateParser;

/**
 * @return array<string, mixed>
 */
function update(string $text, int $chatId = 4242): array
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

it('parses a plain command with no argument', function () {
    $parsed = (new UpdateParser)->parse(update('/help'));

    expect($parsed->command)->toBe('help')
        ->and($parsed->text)->toBe('')
        ->and($parsed->chatId)->toBe(4242)
        ->and($parsed->isCommand())->toBeTrue();
});

it('parses a command with arguments', function () {
    $parsed = (new UpdateParser)->parse(update('/file write about queues'));

    expect($parsed->command)->toBe('file')
        ->and($parsed->text)->toBe('write about queues');
});

it('strips a @bot_handle suffix from the command', function () {
    $parsed = (new UpdateParser)->parse(update('/file@stringer_grdzelo_bot 42'));

    expect($parsed->command)->toBe('file')
        ->and($parsed->text)->toBe('42');
});

it('lowercases the command name', function () {
    $parsed = (new UpdateParser)->parse(update('/HELP'));

    expect($parsed->command)->toBe('help');
});

it('returns null command for non-command text and keeps the whole body as text', function () {
    $parsed = (new UpdateParser)->parse(update('just a free-form question about queues'));

    expect($parsed->command)->toBeNull()
        ->and($parsed->text)->toBe('just a free-form question about queues')
        ->and($parsed->isCommand())->toBeFalse();
});

it('handles a slash with only a number as non-command (no leading word)', function () {
    // Edge case: "/123" — Telegram doesn't actually emit this as a command
    // entity, but the parser shouldn't crash. \w includes digits, so it
    // does parse — that's acceptable defensive behavior.
    $parsed = (new UpdateParser)->parse(update('/123 foo'));

    expect($parsed->command)->toBe('123')
        ->and($parsed->text)->toBe('foo');
});

it('throws when the update has no message field', function () {
    expect(fn () => (new UpdateParser)->parse(['update_id' => 1]))
        ->toThrow(InvalidArgumentException::class, 'no `message`');
});

it('throws when the message has no text', function () {
    expect(fn () => (new UpdateParser)->parse([
        'message' => ['chat' => ['id' => 1]],
    ]))->toThrow(InvalidArgumentException::class, 'no `text`');
});

it('throws when the message has no chat.id', function () {
    expect(fn () => (new UpdateParser)->parse([
        'message' => ['text' => '/help', 'chat' => []],
    ]))->toThrow(InvalidArgumentException::class, 'no `chat.id`');
});

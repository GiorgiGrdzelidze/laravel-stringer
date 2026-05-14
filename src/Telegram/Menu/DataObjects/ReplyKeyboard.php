<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\DataObjects;

/**
 * Value object describing a Telegram custom reply keyboard.
 *
 * `$rows` is a list of rows, each row a list of button labels.
 * Variable widths just like Telegram's API — e.g.
 *
 *     [
 *         ['✍ Generate'],
 *         ['📋 Topics', '📚 Categories'],
 *         ['⚙ Settings'],
 *         ['🌐 Change language'],
 *     ]
 *
 * Serialises into Telegram's `reply_markup` field shape on send.
 */
final readonly class ReplyKeyboard
{
    /**
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public array $rows,
        public bool $resizeKeyboard = true,
        public bool $oneTimeKeyboard = false,
        public bool $selective = false,
    ) {}

    /**
     * Telegram-API-shaped array, ready to be JSON-encoded into `reply_markup`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'keyboard' => array_map(
                static fn (array $row): array => array_map(
                    static fn (string $label): array => ['text' => $label],
                    $row,
                ),
                $this->rows,
            ),
            'resize_keyboard' => $this->resizeKeyboard,
            'one_time_keyboard' => $this->oneTimeKeyboard,
            'selective' => $this->selective,
            'is_persistent' => true,
        ];
    }

    /**
     * Returns the flat list of every button label across all rows. Used by
     * the router to decide whether an incoming message matches a button.
     *
     * @return list<string>
     */
    public function allLabels(): array
    {
        $labels = [];
        foreach ($this->rows as $row) {
            foreach ($row as $label) {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}

<?php

declare(strict_types=1);

namespace Stringer\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One-row-per-chat pending-input buffer.
 *
 * @property int $chat_id
 * @property string $expected_action
 * @property array<string, mixed>|null $payload
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
final class TelegramPendingInput extends Model
{
    protected $primaryKey = 'chat_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->setTable(config('stringer.tables.telegram_pending_inputs', 'telegram_pending_inputs'));
        parent::__construct($attributes);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

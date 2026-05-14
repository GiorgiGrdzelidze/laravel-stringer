<?php

declare(strict_types=1);

namespace Stringer\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Singleton-shaped settings row backing the ManageStringerSettings page.
 *
 * Always row id=1. `current()` self-heals via `firstOrCreate` so a fresh
 * deploy doesn't need a seeder hop. v0.1.0 is write-only from the
 * package's perspective — consumer code (DraftGenerator etc.) still
 * reads env/config; runtime overlay of these values is v0.2.
 *
 * @property int $id
 * @property string|null $voice_card
 * @property int|null $body_word_cap
 * @property int|null $tag_count
 * @property string|null $auto_generate_cron
 * @property string|null $auto_generate_timezone
 * @property array<int, int>|null $allowed_chat_ids
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class StringerSetting extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'body_word_cap' => 'integer',
        'tag_count' => 'integer',
        'allowed_chat_ids' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        $this->setTable((string) config('stringer.tables.stringer_settings', 'stringer_settings'));
        parent::__construct($attributes);
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}

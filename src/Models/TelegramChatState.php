<?php

declare(strict_types=1);

namespace Stringer\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-chat menu state.
 *
 * @property int $chat_id
 * @property string $language
 * @property string $current_path
 * @property int|null $last_menu_message_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TelegramChatState extends Model
{
    protected $primaryKey = 'chat_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $this->setTable(config('stringer.tables.telegram_chat_states', 'telegram_chat_states'));
        parent::__construct($attributes);
    }
}

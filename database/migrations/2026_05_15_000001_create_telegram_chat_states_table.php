<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-chat state for the Telegram menu.
 *
 * One row per chat. Stores the language preference and the user's current
 * position in the menu tree (so "← Back" knows where to go).
 *
 * `chat_id` is a signed BIGINT — Telegram supergroup IDs are negative and
 * exceed 32-bit range.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('stringer.tables.telegram_chat_states', 'telegram_chat_states');

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->bigInteger('chat_id')->primary();
            $blueprint->string('language', 10)->default('en');
            $blueprint->string('current_path', 512)->default('root');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('stringer.tables.telegram_chat_states', 'telegram_chat_states'));
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-chat "I'm waiting for your next message" buffer.
 *
 * When the menu prompts the user to type a free-text hint (e.g. "send your
 * topic hint as the next message"), a row is written here with a short TTL.
 * The next inbound text message from this chat is consumed by the
 * corresponding action and the row is deleted.
 *
 * One row per chat — a new prompt overwrites the previous one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('stringer.tables.telegram_pending_inputs', 'telegram_pending_inputs');

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->bigInteger('chat_id')->primary();
            $blueprint->string('expected_action', 191);
            $blueprint->json('payload')->nullable();
            $blueprint->timestamp('expires_at');
            $blueprint->timestamp('created_at')->useCurrent();

            $blueprint->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('stringer.tables.telegram_pending_inputs', 'telegram_pending_inputs'));
    }
};

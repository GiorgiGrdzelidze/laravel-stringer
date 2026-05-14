<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `last_menu_message_id` so the renderer can delete the bot's previous
 * menu message before sending a new screen. Result: the chat shows exactly
 * one bot message at a time — current screen — instead of a history of
 * every navigation step.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('stringer.tables.telegram_chat_states', 'telegram_chat_states');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->bigInteger('last_menu_message_id')->nullable()->after('current_path');
        });
    }

    public function down(): void
    {
        $table = config('stringer.tables.telegram_chat_states', 'telegram_chat_states');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('last_menu_message_id');
        });
    }
};

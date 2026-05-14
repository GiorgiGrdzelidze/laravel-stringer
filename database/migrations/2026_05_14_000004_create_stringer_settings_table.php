<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row settings table for the ManageStringerSettings Filament page.
 *
 * v0.1.0 persists operator-edited values here but consumer code (DraftGenerator,
 * AutoGenerateWeeklyJob, VerifyTelegramSecret) still reads from env/config.
 * Runtime overlay of these values is v0.2 territory; the table exists so the
 * page does something instead of being a decorative shell.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('stringer.tables.stringer_settings', 'stringer_settings');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->text('voice_card')->nullable();
            $table->unsignedInteger('body_word_cap')->default(800);
            $table->unsignedInteger('tag_count')->default(5);
            $table->string('auto_generate_cron')->default('0 9 * * 1');
            $table->string('auto_generate_timezone')->default('Asia/Tbilisi');
            $table->json('allowed_chat_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('stringer.tables.stringer_settings', 'stringer_settings'));
    }
};

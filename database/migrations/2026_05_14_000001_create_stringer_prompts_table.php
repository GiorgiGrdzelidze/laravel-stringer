<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('stringer.tables.stringer_prompts', 'stringer_prompts');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('locale')->nullable();
            $table->longText('content');
            $table->string('description')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('stringer.tables.stringer_prompts', 'stringer_prompts'));
    }
};

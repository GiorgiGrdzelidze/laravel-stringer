<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('stringer.tables.blog_topics', 'blog_topics');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->bigInteger('requested_by_chat_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $table = config('stringer.tables.blog_topics', 'blog_topics');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('requested_by_chat_id')->nullable()->change();
        });
    }
};

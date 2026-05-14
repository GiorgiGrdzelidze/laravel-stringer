<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G1 reversal (spec 2026-05-14): introduce the per-topic auto-publish
 * toggle and the target status. Default behavior is unchanged — drafts
 * — but operators can now opt a specific topic into auto-publish when
 * the pipeline is trusted for routine recurring content.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('stringer.tables.blog_topics', 'blog_topics');

        Schema::table($table, function (Blueprint $table) {
            $table->boolean('auto_publish')->default(false)->after('requested_by_chat_id');
            $table->string('target_status')->nullable()->default('draft')->after('auto_publish');
        });
    }

    public function down(): void
    {
        $table = config('stringer.tables.blog_topics', 'blog_topics');

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn(['auto_publish', 'target_status']);
        });
    }
};

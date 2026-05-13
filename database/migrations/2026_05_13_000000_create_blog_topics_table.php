<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The FK on `article_id` is added only when the host's articles table
 * already exists at migration time. Hosts whose articles migration ships
 * after Stringer's must either re-run this migration via `migrate:fresh`
 * or add the constraint manually in a follow-up migration — there is no
 * deferred-FK path in v0.1.0.
 */
return new class extends Migration
{
    public function up(): void
    {
        $blogTopics = config('stringer.tables.blog_topics', 'blog_topics');
        $articles = config('stringer.tables.articles', 'articles');

        Schema::create($blogTopics, function (Blueprint $table) {
            $table->id();
            $table->text('hint');
            $table->string('status');
            $table->string('source');
            $table->string('source_ref_type')->nullable();
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->unsignedBigInteger('article_id')->nullable();
            $table->string('generated_by')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('requested_by_chat_id')->nullable();
            $table->timestamp('drafted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['source_ref_type', 'source_ref_id']);
        });

        if (Schema::hasTable($articles)) {
            Schema::table($blogTopics, function (Blueprint $table) use ($articles) {
                $table->foreign('article_id')
                    ->references('id')
                    ->on($articles)
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('stringer.tables.blog_topics', 'blog_topics'));
    }
};

<?php

declare(strict_types=1);

namespace Stringer\Laravel\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;

/**
 * The only sanctioned path for `BlogTopic` status mutations.
 *
 * Controllers, jobs, and Telegram command handlers go through this
 * service; never `$topic->update(['status' => ...])` or `$topic->status = ...`
 * inline. Centralizing the transitions here is what makes future
 * activitylog hooks, metrics, or audit trails a one-place change.
 */
final class TopicQueue
{
    private const ERROR_MAX_LENGTH = 1000;

    public function enqueue(
        string $hint,
        TopicSource $source,
        ?Model $sourceRef = null,
        ?int $chatId = null,
    ): BlogTopic {
        $topic = new BlogTopic;
        $topic->hint = $hint;
        $topic->status = TopicStatus::Queued;
        $topic->source = $source;
        $topic->requested_by_chat_id = $chatId;

        if ($sourceRef !== null) {
            $topic->sourceRef()->associate($sourceRef);
        }

        $topic->save();

        return $topic;
    }

    public function markDrafting(BlogTopic $topic): void
    {
        $topic->status = TopicStatus::Drafting;
        $topic->save();
    }

    public function markDrafted(BlogTopic $topic, Model $article, string $generatedBy): void
    {
        $topic->status = TopicStatus::Drafted;
        $topic->article_id = (int) $article->getKey();
        $topic->generated_by = $generatedBy;
        $topic->drafted_at = Date::now();
        $topic->save();
    }

    public function markFailed(BlogTopic $topic, string $error): void
    {
        $topic->status = TopicStatus::Failed;
        $topic->last_error = mb_substr($error, 0, self::ERROR_MAX_LENGTH);
        $topic->save();
    }

    public function markRejected(BlogTopic $topic): void
    {
        $topic->status = TopicStatus::Rejected;
        $topic->save();
    }
}

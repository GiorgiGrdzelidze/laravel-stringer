<?php

declare(strict_types=1);

namespace Stringer\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\DraftGenerator;
use Stringer\Laravel\Services\TopicQueue;
use Throwable;

/**
 * Queued wrapper around `DraftGenerator::generate`.
 *
 * - `tries = 2`, `backoff = [30]` (one retry 30 seconds after the first
 *   failure; the second failure triggers `failed()` and the topic is
 *   marked Failed).
 * - Idempotency guard: if the topic is already in `Drafting` and its
 *   `updated_at` is within the last 5 minutes, the handler exits early.
 *   Covers the parallel-worker / requeue-while-running race.
 * - On final failure: `TopicQueue::markFailed` records the message,
 *   `Log::channel('daily')` writes a structured entry. Telegram
 *   notification of the operator who requested the topic ships in
 *   Phase 8 alongside the rest of the Telegram surface.
 */
final class GenerateDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const PARALLEL_RETRY_WINDOW_MINUTES = 5;

    public int $tries = 2;

    public function __construct(public readonly int $topicId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30];
    }

    public function handle(DraftGenerator $generator, TopicQueue $queue): void
    {
        $topic = BlogTopic::query()->find($this->topicId);

        if (! $topic instanceof BlogTopic) {
            // Topic was deleted between dispatch and handle. Nothing to do.
            return;
        }

        if ($this->isRecentParallelRetry($topic)) {
            return;
        }

        $queue->markDrafting($topic);
        $generator->generate($topic);
        // DraftGenerator marks the topic Drafted inside its transaction
        // when the host adapter successfully writes; no further work here.
    }

    public function failed(Throwable $exception): void
    {
        $topic = BlogTopic::query()->find($this->topicId);

        if ($topic instanceof BlogTopic) {
            app(TopicQueue::class)->markFailed($topic, $exception->getMessage());
        }

        Log::channel('daily')->error('stringer.generate_draft_job.failed', [
            'topic_id' => $this->topicId,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);

        // TODO Phase 8: notify $topic->requested_by_chat_id via TelegramClient
        // (best-effort; failure to notify must not re-throw from failed()).
    }

    private function isRecentParallelRetry(BlogTopic $topic): bool
    {
        if ($topic->status !== TopicStatus::Drafting) {
            return false;
        }

        $cutoff = Date::now()->subMinutes(self::PARALLEL_RETRY_WINDOW_MINUTES);

        return $topic->updated_at->greaterThan($cutoff);
    }
}

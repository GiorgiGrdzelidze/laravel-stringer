<?php

declare(strict_types=1);

namespace Stringer\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stringer\Laravel\Contracts\ContextBuilder;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Services\TopicQueue;

/**
 * Scheduler-triggered weekly nudge.
 *
 * Two paths:
 *  - If there's a Queued topic, dispatch GenerateDraftJob for the
 *    oldest one — the operator was going to handle it anyway, we just
 *    move them along.
 *  - Otherwise synthesize an `Auto`-source topic from the first
 *    project (or repository, as fallback) entry the host's
 *    ContextBuilder returns. The contract doesn't guarantee any
 *    particular ordering, so "first" is what we promise. Host adapters
 *    that want a specific cadence should return the freshest entry
 *    first. Skipped silently if the context is empty.
 */
final class AutoGenerateWeeklyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(TopicQueue $queue, ContextBuilder $context): void
    {
        $oldestQueued = BlogTopic::query()
            ->where('status', TopicStatus::Queued->value)
            ->orderBy('created_at')
            ->first();

        if ($oldestQueued instanceof BlogTopic) {
            GenerateDraftJob::dispatch($oldestQueued->id);

            return;
        }

        $hint = $this->synthesizeHint($context->build());

        if ($hint === null) {
            return;
        }

        $topic = $queue->enqueue($hint, TopicSource::Auto);
        GenerateDraftJob::dispatch($topic->id);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function synthesizeHint(array $context): ?string
    {
        foreach (['projects', 'repositories'] as $bucket) {
            if (! isset($context[$bucket]) || ! is_array($context[$bucket]) || $context[$bucket] === []) {
                continue;
            }

            $first = reset($context[$bucket]);
            if (! is_array($first) || ! isset($first['title']) || ! is_string($first['title']) || $first['title'] === '') {
                continue;
            }

            return "Write about: {$first['title']}";
        }

        return null;
    }
}

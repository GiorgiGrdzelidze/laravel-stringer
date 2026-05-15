<?php

declare(strict_types=1);

namespace Stringer\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Images\ImageManager;
use Stringer\Laravel\Jobs\GenerateCoverImageJob;
use Stringer\Laravel\Models\BlogTopic;

/**
 * Dispatches a `GenerateCoverImageJob` for every Drafted topic whose
 * article is missing a cover. Use after enabling image generation on
 * an existing install to backfill historical articles.
 *
 * Idempotent: only topics that already have an `article_id` and have
 * already been written by the pipeline are eligible. Hosts whose
 * articles already carry a cover (detected by the host adapter via
 * the media collection) get a silent skip — the cover job is
 * non-fatal and the adapter is free to no-op on already-attached
 * covers.
 *
 * `--driver=` switches the resolved driver per run without mutating
 * config. `--limit=` caps the run so a large queue can be drained
 * in batches.
 */
final class BackfillImagesCommand extends Command
{
    protected $signature = 'stringer:images:backfill
        {--driver= : Override the configured driver for this run (imagen|unsplash|dalle|flux)}
        {--limit= : Stop after dispatching this many jobs}
        {--sync : Run the jobs inline instead of via the queue}';

    protected $description = 'Dispatch cover-image generation for Drafted topics that have no cover yet';

    public function handle(): int
    {
        $driverOverride = $this->option('driver');
        if ($driverOverride !== null && ! in_array($driverOverride, ['imagen', 'unsplash', 'dalle', 'flux'], true)) {
            $this->error("Unknown driver '{$driverOverride}'. Use: imagen, unsplash, dalle, flux.");

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = $limit === null ? null : (int) $limit;
        if ($limit !== null && $limit < 1) {
            $this->error('--limit must be at least 1.');

            return self::FAILURE;
        }

        $query = BlogTopic::query()
            ->where('status', TopicStatus::Drafted->value)
            ->whereNotNull('article_id')
            ->orderBy('id');

        $count = (clone $query)->count();
        $this->info("Eligible Drafted topics: {$count}".($limit !== null ? " (limit {$limit})" : ''));

        if ($count === 0) {
            return self::SUCCESS;
        }

        $dispatched = 0;
        $query->chunkById(50, function ($topics) use (&$dispatched, $limit, $driverOverride) {
            foreach ($topics as $topic) {
                if ($limit !== null && $dispatched >= $limit) {
                    return false;
                }

                $job = new GenerateCoverImageJob($topic->id, $driverOverride);
                if ($this->option('sync')) {
                    $job->handle(
                        app(ImageManager::class),
                        app(PromptBuilder::class),
                        app(ContentTarget::class),
                        app('config'),
                        app(LlmClient::class),
                    );
                } else {
                    GenerateCoverImageJob::dispatch($topic->id, $driverOverride);
                }

                $dispatched++;
                $this->line(sprintf('  • topic #%d → %s', $topic->id, $this->option('sync') ? 'ran' : 'queued'));
            }

            return true;
        });

        $this->info("Dispatched: {$dispatched} job(s).");

        return self::SUCCESS;
    }
}

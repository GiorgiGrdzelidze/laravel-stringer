<?php

declare(strict_types=1);

namespace Stringer\Laravel\Contracts;

use Illuminate\Database\Eloquent\Model;
use Stringer\Laravel\DataObjects\LocalizedDraft;
use Stringer\Laravel\Models\BlogTopic;

/**
 * Host-implemented sink for generated drafts.
 *
 * Implementations MUST honor the per-topic G1 policy: default writes a
 * draft (`status='draft'`, `publish_at=null`); when the operator opts
 * a specific topic into auto-publish via `$topic->auto_publish=true`,
 * write `$topic->target_status` as the status and `now()` as `publish_at`.
 * The package never auto-publishes implicitly.
 */
interface ContentTarget
{
    /**
     * Persist the localized draft and return the newly-created host model.
     *
     * @param  LocalizedDraft  $draft  Field values keyed by `StringerContentField.name`.
     * @param  BlogTopic|null  $topic  Source topic — carries `auto_publish` + `target_status`
     *                                 G1 flags. Optional only to allow ad-hoc writes outside
     *                                 the queue (rare); pipeline writes always pass it.
     */
    public function write(LocalizedDraft $draft, ?BlogTopic $topic = null): Model;

    /**
     * Admin-side edit URL for the persisted record (Filament resource URL,
     * Nova URL, custom path — host's choice). Used by the Telegram client
     * to link the human reviewer to the draft.
     */
    public function editUrl(Model $record): string;
}

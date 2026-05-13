<?php

declare(strict_types=1);

namespace Stringer\Laravel\Contracts;

use Illuminate\Database\Eloquent\Model;
use Stringer\Laravel\DataObjects\LocalizedDraft;

/**
 * Host-implemented sink for generated drafts.
 *
 * Implementations MUST write the record as a draft — `status='draft'` and
 * `publish_at=null` on the host's content model. The package never publishes.
 */
interface ContentTarget
{
    /**
     * Persist the localized draft and return the newly-created host model.
     */
    public function write(LocalizedDraft $draft): Model;

    /**
     * Admin-side edit URL for the persisted record (Filament resource URL,
     * Nova URL, custom path — host's choice). Used by the Telegram client
     * to link the human reviewer to the draft.
     */
    public function editUrl(Model $record): string;
}

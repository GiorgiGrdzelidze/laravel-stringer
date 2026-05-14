<?php

declare(strict_types=1);

namespace Stringer\Laravel\Tests\Doubles;

use Illuminate\Database\Eloquent\Model;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\DataObjects\LocalizedDraft;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;
use Stringer\Laravel\Models\BlogTopic;

/**
 * Captures the arguments passed to `write()` for assertion in tests, and
 * returns a freshly-saved `BlogTopic` so `TopicQueue::markDrafted` has a
 * model with a primary key to link against.
 */
final class CapturingContentTarget implements ContentTarget
{
    public ?LocalizedDraft $capturedDraft = null;

    public ?BlogTopic $capturedTopic = null;

    public bool $wasCalled = false;

    public function write(LocalizedDraft $draft, ?BlogTopic $topic = null): Model
    {
        $this->capturedDraft = $draft;
        $this->capturedTopic = $topic;
        $this->wasCalled = true;

        return BlogTopic::create([
            'hint' => 'stub-article-stand-in',
            'status' => TopicStatus::Drafted,
            'source' => TopicSource::Auto,
        ]);
    }

    public function editUrl(Model $record): string
    {
        return 'https://example.test/admin/articles/'.$record->getKey();
    }
}

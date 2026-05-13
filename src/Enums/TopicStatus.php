<?php

declare(strict_types=1);

namespace Stringer\Laravel\Enums;

/**
 * Lifecycle states of a BlogTopic, from intake to outcome.
 *
 * Status transitions go through `Services\TopicQueue` — never `Topic::update(['status' => ...])`.
 */
enum TopicStatus: string
{
    case Queued = 'queued';
    case Drafting = 'drafting';
    case Drafted = 'drafted';
    case Rejected = 'rejected';
    case Failed = 'failed';
}

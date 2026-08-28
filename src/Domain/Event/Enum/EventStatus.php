<?php

namespace QOR\App\Domain\Event\Enum;

enum EventStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}

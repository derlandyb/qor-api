<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Published = 'published';
    case ChangesRequested = 'changes_requested';
    case Cancelled = 'cancelled';
    case Finished = 'finished';
}

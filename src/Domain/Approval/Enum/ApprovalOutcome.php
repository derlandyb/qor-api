<?php

namespace QOR\App\Domain\Approval\Enum;

enum ApprovalOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case ForceCancelled = 'force_cancelled';
}

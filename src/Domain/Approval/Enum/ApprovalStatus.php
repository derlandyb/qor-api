<?php

namespace QOR\App\Domain\Approval\Enum;

enum ApprovalStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}

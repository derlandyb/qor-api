<?php

namespace App\Enums;

enum EventRevisionStatus: string
{
    case PendingApproval = 'pending_approval';
    case ChangesRequested = 'changes_requested';
}

<?php

namespace App\Enums;

enum VerificationApplicationStatus: string
{
    case PendingReview = 'pending_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
}

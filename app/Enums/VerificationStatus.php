<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case PendingReview = 'pending_review';
    case Verified = 'verified';
}

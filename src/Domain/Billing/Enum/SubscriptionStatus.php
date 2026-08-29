<?php

namespace QOR\App\Domain\Billing\Enum;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case CancelledPendingReset = 'cancelled_pending_reset';
}

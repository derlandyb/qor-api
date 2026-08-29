<?php

namespace QOR\App\Domain\Billing\Enum;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';
}

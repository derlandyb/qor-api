<?php

namespace QOR\App\Domain\Billing\Enum;

enum SubscribableType: string
{
    case Venue = 'venue';
    case Promoter = 'promoter';
}

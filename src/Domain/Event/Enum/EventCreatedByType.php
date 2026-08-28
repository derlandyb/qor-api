<?php

namespace QOR\App\Domain\Event\Enum;

enum EventCreatedByType: string
{
    case VenueAdmin = 'venue_admin';
    case Promoter = 'promoter';
}

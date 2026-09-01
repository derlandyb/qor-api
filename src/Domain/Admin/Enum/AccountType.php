<?php

namespace QOR\App\Domain\Admin\Enum;

/**
 * What kind of admin-guard account this is — distinct from
 * `Domain\Event\Enum\EventCreatedByType`, which describes who created an
 * Event, not who a session belongs to. Kept as the Admin domain's own
 * vocabulary rather than reusing Event's enum values on `GET /me`'s response.
 */
enum AccountType: string
{
    case SuperAdmin = 'super_admin';
    case VenueAdmin = 'venue_admin';
    case Promoter = 'promoter';
}

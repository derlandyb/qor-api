<?php

namespace QOR\App\Http\Support;

use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

/**
 * Resolves the Venue or Promoter owned by an authenticated admin user.
 * Shared across AdminV1 controllers that need to know "which organizer is this?".
 */
class OrganizerIdentityResolver
{
    /**
     * @return array{0: EventCreatedByType, 1: int}
     */
    public function resolve(AdminUserModel $admin): array
    {
        $venue = VenueModel::where('venue_admin_user_id', $admin->id)->first();
        if ($venue !== null) {
            return [EventCreatedByType::VenueAdmin, (int) $venue->id];
        }

        $promoter = PromoterModel::where('user_id', $admin->id)->first();
        if ($promoter !== null) {
            return [EventCreatedByType::Promoter, (int) $promoter->id];
        }

        abort(403, 'Conta não é uma Venue ou Promoter registrada.');
    }
}

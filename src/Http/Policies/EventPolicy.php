<?php

namespace QOR\App\Http\Policies;

use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

/**
 * $organizer is the Venue or Promoter that actually created the event — the
 * caller resolves it (from $event->created_by_type/created_by_id) and passes
 * it in, keeping this policy a pure function of its arguments (per
 * ARCHITECTURE.md §8.5, no query building inside the policy itself).
 */
class EventPolicy
{
    public function create(AdminUserModel $admin, VenueModel|PromoterModel $organizer): bool
    {
        return $this->owns($admin, $organizer) && $this->isApproved($organizer);
    }

    /**
     * ADMIN-24: a Promoter merely tagged on the event (contact-display-only)
     * but not its creator is denied edit rights, even if that Promoter is
     * itself approved and owned by $admin.
     */
    public function update(AdminUserModel $admin, EventModel $event, VenueModel|PromoterModel $organizer): bool
    {
        if (! $this->isCreatorOf($event, $organizer)) {
            return false;
        }

        return $this->owns($admin, $organizer) && $this->isApproved($organizer);
    }

    public function submit(AdminUserModel $admin, EventModel $event, VenueModel|PromoterModel $organizer): bool
    {
        return $this->update($admin, $event, $organizer);
    }

    private function isCreatorOf(EventModel $event, VenueModel|PromoterModel $organizer): bool
    {
        $expectedType = $organizer instanceof VenueModel
            ? EventCreatedByType::VenueAdmin
            : EventCreatedByType::Promoter;

        return $event->created_by_type === $expectedType && $event->created_by_id === $organizer->id;
    }

    private function owns(AdminUserModel $admin, VenueModel|PromoterModel $organizer): bool
    {
        $ownerId = $organizer instanceof VenueModel ? $organizer->venue_admin_user_id : $organizer->user_id;

        return $ownerId === $admin->id;
    }

    private function isApproved(VenueModel|PromoterModel $organizer): bool
    {
        return $organizer->approval_status === ApprovalStatus::Approved;
    }
}

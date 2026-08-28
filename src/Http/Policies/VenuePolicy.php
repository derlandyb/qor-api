<?php

namespace QOR\App\Http\Policies;

use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

/**
 * ADMIN-20: a Venue account that is Pending Approval or Suspended is blocked
 * from every write action, regardless of ownership.
 */
class VenuePolicy
{
    public function update(AdminUserModel $admin, VenueModel $venue): bool
    {
        return $this->owns($admin, $venue) && $venue->approval_status === ApprovalStatus::Approved;
    }

    public function delete(AdminUserModel $admin, VenueModel $venue): bool
    {
        return $this->update($admin, $venue);
    }

    private function owns(AdminUserModel $admin, VenueModel $venue): bool
    {
        return $venue->venue_admin_user_id === $admin->id;
    }
}

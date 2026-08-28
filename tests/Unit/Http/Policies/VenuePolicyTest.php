<?php

namespace Tests\Unit\Http\Policies;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Http\Policies\VenuePolicy;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class VenuePolicyTest extends TestCase
{
    private function makeAdmin(int $id): AdminUserModel
    {
        $admin = new AdminUserModel();
        $admin->id = $id;

        return $admin;
    }

    private function makeVenue(int $ownerId, ApprovalStatus $status): VenueModel
    {
        $venue = new VenueModel();
        $venue->venue_admin_user_id = $ownerId;
        $venue->approval_status = $status;

        return $venue;
    }

    public function test_GIVEN_a_pending_approval_owner_WHEN_checking_update_THEN_it_is_denied(): void
    {
        $policy = new VenuePolicy();
        $admin = $this->makeAdmin(1);
        $venue = $this->makeVenue(1, ApprovalStatus::PendingApproval);

        $this->assertFalse($policy->update($admin, $venue));
    }

    public function test_GIVEN_a_suspended_owner_WHEN_checking_update_THEN_it_is_denied(): void
    {
        $policy = new VenuePolicy();
        $admin = $this->makeAdmin(1);
        $venue = $this->makeVenue(1, ApprovalStatus::Suspended);

        $this->assertFalse($policy->update($admin, $venue));
    }

    public function test_GIVEN_an_approved_non_owner_WHEN_checking_update_THEN_it_is_denied(): void
    {
        $policy = new VenuePolicy();
        $admin = $this->makeAdmin(2);
        $venue = $this->makeVenue(1, ApprovalStatus::Approved);

        $this->assertFalse($policy->update($admin, $venue));
    }

    public function test_GIVEN_an_approved_owner_WHEN_checking_update_THEN_it_is_allowed(): void
    {
        $policy = new VenuePolicy();
        $admin = $this->makeAdmin(1);
        $venue = $this->makeVenue(1, ApprovalStatus::Approved);

        $this->assertTrue($policy->update($admin, $venue));
    }

    public function test_GIVEN_an_approved_owner_WHEN_checking_delete_THEN_it_is_allowed(): void
    {
        $policy = new VenuePolicy();
        $admin = $this->makeAdmin(1);
        $venue = $this->makeVenue(1, ApprovalStatus::Approved);

        $this->assertTrue($policy->delete($admin, $venue));
    }

    public function test_GIVEN_a_non_owner_WHEN_checking_delete_THEN_it_is_denied(): void
    {
        $policy = new VenuePolicy();
        $admin = $this->makeAdmin(2);
        $venue = $this->makeVenue(1, ApprovalStatus::Approved);

        $this->assertFalse($policy->delete($admin, $venue));
    }
}

<?php

namespace Tests\Unit\Http\Policies;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Http\Policies\EventPolicy;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class EventPolicyTest extends TestCase
{
    private function makeAdmin(int $id): AdminUserModel
    {
        $admin = new AdminUserModel();
        $admin->id = $id;

        return $admin;
    }

    private function makeVenue(int $id, int $ownerId, ApprovalStatus $status): VenueModel
    {
        $venue = new VenueModel();
        $venue->id = $id;
        $venue->venue_admin_user_id = $ownerId;
        $venue->approval_status = $status;

        return $venue;
    }

    private function makePromoter(int $id, int $ownerId, ApprovalStatus $status): PromoterModel
    {
        $promoter = new PromoterModel();
        $promoter->id = $id;
        $promoter->user_id = $ownerId;
        $promoter->approval_status = $status;

        return $promoter;
    }

    private function makeEvent(EventCreatedByType $type, int $createdById): EventModel
    {
        $event = new EventModel();
        $event->created_by_type = $type;
        $event->created_by_id = $createdById;

        return $event;
    }

    public function test_GIVEN_an_approved_owning_venue_WHEN_checking_update_THEN_it_is_allowed(): void
    {
        $policy = new EventPolicy();
        $admin = $this->makeAdmin(1);
        $venue = $this->makeVenue(10, 1, ApprovalStatus::Approved);
        $event = $this->makeEvent(EventCreatedByType::VenueAdmin, 10);

        $this->assertTrue($policy->update($admin, $event, $venue));
    }

    public function test_GIVEN_a_tagged_promoter_without_ownership_WHEN_checking_update_THEN_it_is_denied(): void
    {
        $policy = new EventPolicy();
        $admin = $this->makeAdmin(2);
        $venue = $this->makeVenue(10, 1, ApprovalStatus::Approved);
        $event = $this->makeEvent(EventCreatedByType::VenueAdmin, 10);
        $taggedPromoter = $this->makePromoter(20, 2, ApprovalStatus::Approved);

        $this->assertFalse($policy->update($admin, $event, $taggedPromoter));
    }

    public function test_GIVEN_a_pending_approval_organizer_WHEN_checking_create_THEN_it_is_denied(): void
    {
        $policy = new EventPolicy();
        $admin = $this->makeAdmin(1);
        $venue = $this->makeVenue(10, 1, ApprovalStatus::PendingApproval);

        $this->assertFalse($policy->create($admin, $venue));
    }

    public function test_GIVEN_an_approved_owning_organizer_WHEN_checking_create_THEN_it_is_allowed(): void
    {
        $policy = new EventPolicy();
        $admin = $this->makeAdmin(1);
        $venue = $this->makeVenue(10, 1, ApprovalStatus::Approved);

        $this->assertTrue($policy->create($admin, $venue));
    }

    public function test_GIVEN_a_non_owning_admin_WHEN_checking_create_THEN_it_is_denied(): void
    {
        $policy = new EventPolicy();
        $admin = $this->makeAdmin(99);
        $venue = $this->makeVenue(10, 1, ApprovalStatus::Approved);

        $this->assertFalse($policy->create($admin, $venue));
    }

    public function test_GIVEN_an_approved_owning_promoter_creator_WHEN_checking_submit_THEN_it_is_allowed(): void
    {
        $policy = new EventPolicy();
        $admin = $this->makeAdmin(1);
        $promoter = $this->makePromoter(20, 1, ApprovalStatus::Approved);
        $event = $this->makeEvent(EventCreatedByType::Promoter, 20);

        $this->assertTrue($policy->submit($admin, $event, $promoter));
    }
}

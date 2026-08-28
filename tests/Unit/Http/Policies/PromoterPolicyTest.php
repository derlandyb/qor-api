<?php

namespace Tests\Unit\Http\Policies;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Http\Policies\PromoterPolicy;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;

class PromoterPolicyTest extends TestCase
{
    private function makeAdmin(int $id): AdminUserModel
    {
        $admin = new AdminUserModel();
        $admin->id = $id;

        return $admin;
    }

    private function makePromoter(int $ownerId, ApprovalStatus $status): PromoterModel
    {
        $promoter = new PromoterModel();
        $promoter->user_id = $ownerId;
        $promoter->approval_status = $status;

        return $promoter;
    }

    public function test_GIVEN_a_pending_approval_owner_WHEN_checking_update_THEN_it_is_denied(): void
    {
        $policy = new PromoterPolicy();
        $admin = $this->makeAdmin(1);
        $promoter = $this->makePromoter(1, ApprovalStatus::PendingApproval);

        $this->assertFalse($policy->update($admin, $promoter));
    }

    public function test_GIVEN_a_suspended_owner_WHEN_checking_update_THEN_it_is_denied(): void
    {
        $policy = new PromoterPolicy();
        $admin = $this->makeAdmin(1);
        $promoter = $this->makePromoter(1, ApprovalStatus::Suspended);

        $this->assertFalse($policy->update($admin, $promoter));
    }

    public function test_GIVEN_an_approved_owner_WHEN_checking_update_THEN_it_is_allowed(): void
    {
        $policy = new PromoterPolicy();
        $admin = $this->makeAdmin(1);
        $promoter = $this->makePromoter(1, ApprovalStatus::Approved);

        $this->assertTrue($policy->update($admin, $promoter));
    }

    public function test_GIVEN_an_approved_non_owner_WHEN_checking_update_THEN_it_is_denied(): void
    {
        $policy = new PromoterPolicy();
        $admin = $this->makeAdmin(2);
        $promoter = $this->makePromoter(1, ApprovalStatus::Approved);

        $this->assertFalse($policy->update($admin, $promoter));
    }

    public function test_GIVEN_an_approved_owner_WHEN_checking_delete_THEN_it_is_allowed(): void
    {
        $policy = new PromoterPolicy();
        $admin = $this->makeAdmin(1);
        $promoter = $this->makePromoter(1, ApprovalStatus::Approved);

        $this->assertTrue($policy->delete($admin, $promoter));
    }
}

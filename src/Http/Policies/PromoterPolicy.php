<?php

namespace QOR\App\Http\Policies;

use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;

/**
 * ADMIN-20: a Promoter account that is Pending Approval or Suspended is blocked
 * from every write action, regardless of ownership.
 */
class PromoterPolicy
{
    public function update(AdminUserModel $admin, PromoterModel $promoter): bool
    {
        return $this->owns($admin, $promoter) && $promoter->approval_status === ApprovalStatus::Approved;
    }

    public function delete(AdminUserModel $admin, PromoterModel $promoter): bool
    {
        return $this->update($admin, $promoter);
    }

    private function owns(AdminUserModel $admin, PromoterModel $promoter): bool
    {
        return $promoter->user_id === $admin->id;
    }
}

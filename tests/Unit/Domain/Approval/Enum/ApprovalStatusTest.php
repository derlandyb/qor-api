<?php

namespace Tests\Unit\Domain\Approval\Enum;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use ValueError;

class ApprovalStatusTest extends TestCase
{
    public function test_GIVEN_a_valid_raw_value_WHEN_cast_THEN_it_resolves_to_the_correct_case(): void
    {
        $this->assertSame(ApprovalStatus::PendingApproval, ApprovalStatus::from('pending_approval'));
        $this->assertSame(ApprovalStatus::Approved, ApprovalStatus::from('approved'));
        $this->assertSame(ApprovalStatus::Rejected, ApprovalStatus::from('rejected'));
        $this->assertSame(ApprovalStatus::Suspended, ApprovalStatus::from('suspended'));
    }

    public function test_GIVEN_an_invalid_raw_value_WHEN_cast_THEN_it_throws(): void
    {
        $this->expectException(ValueError::class);

        ApprovalStatus::from('not_a_status');
    }
}

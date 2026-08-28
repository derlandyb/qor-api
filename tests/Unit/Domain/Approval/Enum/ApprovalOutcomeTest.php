<?php

namespace Tests\Unit\Domain\Approval\Enum;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use ValueError;

class ApprovalOutcomeTest extends TestCase
{
    public function test_GIVEN_a_valid_raw_value_WHEN_cast_THEN_it_resolves_to_the_correct_case(): void
    {
        $this->assertSame(ApprovalOutcome::Approved, ApprovalOutcome::from('approved'));
        $this->assertSame(ApprovalOutcome::Rejected, ApprovalOutcome::from('rejected'));
        $this->assertSame(ApprovalOutcome::Suspended, ApprovalOutcome::from('suspended'));
        $this->assertSame(ApprovalOutcome::ForceCancelled, ApprovalOutcome::from('force_cancelled'));
    }

    public function test_GIVEN_an_invalid_raw_value_WHEN_cast_THEN_it_throws(): void
    {
        $this->expectException(ValueError::class);

        ApprovalOutcome::from('not_an_outcome');
    }
}

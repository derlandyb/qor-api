<?php

namespace Tests\Unit\Domain\Promoter;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Promoter\Promoter;

class PromoterTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_entity_is_valid_and_defaults_to_pending_approval(): void
    {
        $promoter = new Promoter(
            id: 1,
            userId: 10,
            name: 'Produções Maré',
            contactPhone: '27999999999',
            contactEmail: 'contato@mare.com',
        );

        $this->assertSame(ApprovalStatus::PendingApproval, $promoter->approvalStatus);
        $this->assertFalse($promoter->canPublish());
    }

    public function test_GIVEN_an_empty_name_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Promoter(
            id: null,
            userId: 10,
            name: '',
            contactPhone: '27999999999',
            contactEmail: 'contato@mare.com',
        );
    }

    public function test_GIVEN_an_invalid_contact_email_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Promoter(
            id: null,
            userId: 10,
            name: 'Produções Maré',
            contactPhone: '27999999999',
            contactEmail: 'not-an-email',
        );
    }

    public function test_GIVEN_an_approved_promoter_WHEN_checking_publish_eligibility_THEN_it_can_publish(): void
    {
        $promoter = new Promoter(
            id: 1,
            userId: 10,
            name: 'Produções Maré',
            contactPhone: '27999999999',
            contactEmail: 'contato@mare.com',
            approvalStatus: ApprovalStatus::Approved,
        );

        $this->assertTrue($promoter->canPublish());
    }
}

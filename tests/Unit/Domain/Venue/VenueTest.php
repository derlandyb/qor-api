<?php

namespace Tests\Unit\Domain\Venue;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Venue\Venue;

class VenueTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_entity_is_valid_and_defaults_to_pending_approval(): void
    {
        $venue = new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Clube da Esquina',
            description: 'Casa de shows no centro.',
            address: 'Rua das Flores, 100',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@clube.com',
        );

        $this->assertSame(ApprovalStatus::PendingApproval, $venue->approvalStatus);
        $this->assertFalse($venue->canPublish());
    }

    public function test_GIVEN_an_empty_name_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Venue(
            id: null,
            venueAdminUserId: 10,
            name: '',
            description: 'Casa de shows no centro.',
            address: 'Rua das Flores, 100',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@clube.com',
        );
    }

    public function test_GIVEN_an_invalid_contact_email_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Venue(
            id: null,
            venueAdminUserId: 10,
            name: 'Clube da Esquina',
            description: 'Casa de shows no centro.',
            address: 'Rua das Flores, 100',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'not-an-email',
        );
    }

    public function test_GIVEN_an_approved_venue_WHEN_checking_publish_eligibility_THEN_it_can_publish(): void
    {
        $venue = new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Clube da Esquina',
            description: 'Casa de shows no centro.',
            address: 'Rua das Flores, 100',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@clube.com',
            approvalStatus: ApprovalStatus::Approved,
        );

        $this->assertTrue($venue->canPublish());
    }
}

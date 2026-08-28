<?php

namespace Tests\Unit\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\User\ConsentRecord;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;

class ConsentRecordTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_entity_is_valid(): void
    {
        $record = new ConsentRecord(
            id: 1,
            consentableType: ConsentableType::User,
            consentableId: 10,
            consentType: ConsentType::Terms,
            policyVersion: '1.0',
            acceptedAt: new DateTimeImmutable(),
        );

        $this->assertSame(ConsentType::Terms, $record->consentType);
    }

    public function test_GIVEN_an_admin_user_consentable_WHEN_constructing_THEN_entity_is_valid(): void
    {
        $record = new ConsentRecord(
            id: 1,
            consentableType: ConsentableType::AdminUser,
            consentableId: 10,
            consentType: ConsentType::Terms,
            policyVersion: '1.0',
            acceptedAt: new DateTimeImmutable(),
        );

        $this->assertSame(ConsentableType::AdminUser, $record->consentableType);
    }

    public function test_GIVEN_an_empty_policy_version_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConsentRecord(
            id: null,
            consentableType: ConsentableType::User,
            consentableId: 10,
            consentType: ConsentType::Location,
            policyVersion: '',
            acceptedAt: new DateTimeImmutable(),
        );
    }
}

<?php

namespace Tests\Unit\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\ConsentRecord;
use QOR\App\Domain\User\Enum\AddressSource;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\UserAddress;

final class UserAddressTest extends TestCase
{
    public function test_GIVEN_a_manual_address_WHEN_creating_it_THEN_no_location_consent_is_required(): void
    {
        $address = new UserAddress(
            id: null,
            userId: 1,
            city: City::Vitoria,
            state: 'ES',
            source: AddressSource::Manual,
        );

        $this->assertNull($address->locationConsentGivenAt);
        $this->assertSame(AddressSource::Manual, $address->source);
    }

    public function test_GIVEN_a_device_location_address_without_recorded_consent_WHEN_creating_it_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UserAddress(
            id: null,
            userId: 1,
            city: City::Vitoria,
            state: 'ES',
            source: AddressSource::DeviceLocation,
        );
    }

    public function test_GIVEN_a_device_location_address_with_consent_WHEN_creating_it_THEN_the_radius_and_consent_timestamp_are_kept(): void
    {
        $consentGivenAt = new DateTimeImmutable('2026-01-01T00:00:00Z');

        $address = new UserAddress(
            id: null,
            userId: 1,
            city: City::Vitoria,
            state: 'ES',
            source: AddressSource::DeviceLocation,
            radiusKm: 10,
            locationConsentGivenAt: $consentGivenAt,
        );

        $this->assertSame(10, $address->radiusKm);
        $this->assertSame($consentGivenAt, $address->locationConsentGivenAt);
    }

    public function test_GIVEN_a_fan_granting_device_location_consent_WHEN_recording_it_THEN_it_is_a_distinct_ConsentRecord_row_from_general_terms_acceptance(): void
    {
        $termsAcceptance = new ConsentRecord(
            id: null,
            consentableType: ConsentableType::User,
            consentableId: 1,
            consentType: ConsentType::Terms,
            policyVersion: '1.0',
            acceptedAt: new DateTimeImmutable(),
        );

        $locationConsent = new ConsentRecord(
            id: null,
            consentableType: ConsentableType::User,
            consentableId: 1,
            consentType: ConsentType::Location,
            policyVersion: '1.0',
            acceptedAt: new DateTimeImmutable(),
        );

        $this->assertNotSame($termsAcceptance->consentType, $locationConsent->consentType);
        $this->assertSame(ConsentType::Location, $locationConsent->consentType);
        $this->assertSame(ConsentType::Terms, $termsAcceptance->consentType);
    }
}

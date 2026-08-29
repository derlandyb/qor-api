<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\Enum\AddressSource;
use QOR\App\Domain\User\UserAddress;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentUserAddressRepository;
use Tests\TestCase;

class EloquentUserAddressRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_no_address_row_WHEN_finding_by_user_id_THEN_it_returns_null(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentUserAddressRepository();

        $this->assertNull($repository->findByUserId($user->id));
    }

    public function test_GIVEN_a_new_manual_address_WHEN_saving_THEN_it_is_persisted_and_findable(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentUserAddressRepository();

        $address = new UserAddress(
            id: null,
            userId: $user->id,
            city: City::Vitoria,
            state: 'ES',
            source: AddressSource::Manual,
            radiusKm: 5,
        );

        $repository->save($address);

        $found = $repository->findByUserId($user->id);

        $this->assertNotNull($found);
        $this->assertSame(City::Vitoria, $found->city);
        $this->assertSame(5, $found->radiusKm);
        $this->assertNull($found->locationConsentGivenAt);
    }

    public function test_GIVEN_an_existing_address_WHEN_saving_an_updated_version_THEN_it_updates_the_same_row_rather_than_creating_a_new_one(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentUserAddressRepository();

        $repository->save(new UserAddress(id: null, userId: $user->id, city: City::Vitoria, state: 'ES'));
        $repository->save(new UserAddress(id: null, userId: $user->id, city: City::Serra, state: 'ES'));

        $this->assertDatabaseCount('user_addresses', 1);
        $this->assertSame(City::Serra, $repository->findByUserId($user->id)->city);
    }

    public function test_GIVEN_a_device_location_address_with_consent_WHEN_saving_THEN_the_consent_timestamp_is_persisted(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentUserAddressRepository();
        $consentGivenAt = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        $repository->save(new UserAddress(
            id: null,
            userId: $user->id,
            city: City::Vitoria,
            state: 'ES',
            source: AddressSource::DeviceLocation,
            locationConsentGivenAt: $consentGivenAt,
        ));

        $found = $repository->findByUserId($user->id);

        $this->assertNotNull($found->locationConsentGivenAt);
        $this->assertSame(
            $consentGivenAt->getTimestamp(),
            $found->locationConsentGivenAt->getTimestamp(),
        );
    }

    public function test_GIVEN_multiple_fans_with_addresses_WHEN_listing_all_with_address_THEN_all_of_them_are_returned(): void
    {
        $repository = new EloquentUserAddressRepository();
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();

        $repository->save(new UserAddress(id: null, userId: $userA->id, city: City::Vitoria, state: 'ES'));
        $repository->save(new UserAddress(id: null, userId: $userB->id, city: City::Serra, state: 'ES'));

        $addresses = $repository->listAllWithAddress();

        $this->assertCount(2, $addresses);
    }

    public function test_GIVEN_no_addresses_WHEN_listing_all_with_address_THEN_an_empty_list_is_returned(): void
    {
        $repository = new EloquentUserAddressRepository();

        $this->assertSame([], $repository->listAllWithAddress());
    }
}

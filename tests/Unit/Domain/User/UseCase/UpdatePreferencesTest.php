<?php

namespace Tests\Unit\Domain\User\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\AddressSource;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\UseCase\UpdatePreferences;
use QOR\App\Domain\User\UserAddress;
use QOR\App\Domain\User\UserAddressRepository;
use QOR\App\Domain\User\UserFavoriteGenreRepository;

final class UpdatePreferencesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_no_device_location_permission_WHEN_setting_a_manual_address_THEN_it_saves_with_manual_source(): void
    {
        $addresses = Mockery::mock(UserAddressRepository::class);
        $addresses->shouldReceive('findByUserId')->once()->with(1)->andReturn(null);
        $addresses->shouldReceive('save')->once()->with(Mockery::on(
            fn (UserAddress $a) => $a->source === AddressSource::Manual && $a->city === City::Vitoria
        ))->andReturnUsing(fn (UserAddress $a) => $a);

        $consents = Mockery::mock(ConsentRepository::class);
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $useCase->setAddress(1, City::Vitoria, 'ES', 'Rua A', '10');
    }

    public function test_GIVEN_location_consent_is_granted_WHEN_setting_it_THEN_a_location_consent_record_is_created(): void
    {
        $addresses = Mockery::mock(UserAddressRepository::class);
        $consents = Mockery::mock(ConsentRepository::class);
        $consents->shouldReceive('record')->once()
            ->with(ConsentableType::User, 1, ConsentType::Location, '1.0')
            ->andReturn(new \QOR\App\Domain\User\ConsentRecord(
                id: null,
                consentableType: ConsentableType::User,
                consentableId: 1,
                consentType: ConsentType::Location,
                policyVersion: '1.0',
                acceptedAt: new \DateTimeImmutable(),
            ));
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $useCase->setDeviceLocationConsent(1, true, '1.0');
    }

    public function test_GIVEN_no_policy_version_is_supplied_WHEN_granting_location_consent_THEN_it_throws(): void
    {
        $addresses = Mockery::mock(UserAddressRepository::class);
        $consents = Mockery::mock(ConsentRepository::class);
        $consents->shouldNotReceive('record');
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $this->expectException(InvalidArgumentException::class);

        $useCase->setDeviceLocationConsent(1, true);
    }

    public function test_GIVEN_a_device_location_address_WHEN_revoking_consent_THEN_it_falls_back_to_manual_and_clears_consent_timestamp(): void
    {
        $deviceAddress = new UserAddress(
            id: 5,
            userId: 1,
            city: City::Serra,
            state: 'ES',
            source: AddressSource::DeviceLocation,
            latitude: -20.1,
            longitude: -40.3,
            locationConsentGivenAt: new \DateTimeImmutable(),
        );

        $addresses = Mockery::mock(UserAddressRepository::class);
        $addresses->shouldReceive('findByUserId')->once()->with(1)->andReturn($deviceAddress);
        $addresses->shouldReceive('save')->once()->with(Mockery::on(
            fn (UserAddress $a) => $a->source === AddressSource::Manual && $a->locationConsentGivenAt === null
        ))->andReturnUsing(fn (UserAddress $a) => $a);

        $consents = Mockery::mock(ConsentRepository::class);
        $consents->shouldReceive('revoke')->once()->with(ConsentableType::User, 1, ConsentType::Location);
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $useCase->setDeviceLocationConsent(1, false);
    }

    public function test_GIVEN_a_manual_address_already_present_WHEN_revoking_location_consent_THEN_the_address_is_left_untouched(): void
    {
        $manualAddress = new UserAddress(id: 5, userId: 1, city: City::Serra, state: 'ES', source: AddressSource::Manual);

        $addresses = Mockery::mock(UserAddressRepository::class);
        $addresses->shouldReceive('findByUserId')->once()->with(1)->andReturn($manualAddress);
        $addresses->shouldNotReceive('save');

        $consents = Mockery::mock(ConsentRepository::class);
        $consents->shouldReceive('revoke')->once()->with(ConsentableType::User, 1, ConsentType::Location);
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $useCase->setDeviceLocationConsent(1, false);
    }

    public function test_GIVEN_no_favorite_genres_chosen_WHEN_setting_an_empty_list_THEN_it_is_a_valid_default_not_an_error(): void
    {
        $addresses = Mockery::mock(UserAddressRepository::class);
        $consents = Mockery::mock(ConsentRepository::class);
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);
        $favoriteGenres->shouldReceive('replaceForUser')->once()->with(1, []);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $useCase->setFavoriteGenres(1, []);
    }

    public function test_GIVEN_favorite_genres_chosen_WHEN_setting_them_THEN_they_are_persisted(): void
    {
        $addresses = Mockery::mock(UserAddressRepository::class);
        $consents = Mockery::mock(ConsentRepository::class);
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);
        $favoriteGenres->shouldReceive('replaceForUser')->once()->with(1, [3, 4]);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $useCase->setFavoriteGenres(1, [3, 4]);
    }

    public function test_GIVEN_an_existing_address_WHEN_setting_the_search_radius_THEN_it_updates_just_the_radius(): void
    {
        $existing = new UserAddress(id: 5, userId: 1, city: City::Serra, state: 'ES');

        $addresses = Mockery::mock(UserAddressRepository::class);
        $addresses->shouldReceive('findByUserId')->once()->with(1)->andReturn($existing);
        $addresses->shouldReceive('save')->once()->with(Mockery::on(
            fn (UserAddress $a) => $a->radiusKm === 15
        ))->andReturnUsing(fn (UserAddress $a) => $a);

        $consents = Mockery::mock(ConsentRepository::class);
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $useCase->setSearchRadius(1, 15);
    }

    public function test_GIVEN_no_address_set_yet_WHEN_setting_the_search_radius_THEN_it_throws(): void
    {
        $addresses = Mockery::mock(UserAddressRepository::class);
        $addresses->shouldReceive('findByUserId')->once()->with(1)->andReturn(null);
        $addresses->shouldNotReceive('save');

        $consents = Mockery::mock(ConsentRepository::class);
        $favoriteGenres = Mockery::mock(UserFavoriteGenreRepository::class);

        $useCase = new UpdatePreferences($addresses, $consents, $favoriteGenres);

        $this->expectException(InvalidArgumentException::class);

        $useCase->setSearchRadius(1, 15);
    }
}

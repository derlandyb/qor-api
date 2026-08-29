<?php

namespace QOR\App\Domain\User\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\AddressSource;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\UserAddress;
use QOR\App\Domain\User\UserAddressRepository;
use QOR\App\Domain\User\UserFavoriteGenreRepository;

final class UpdatePreferences
{
    public function __construct(
        private readonly UserAddressRepository $addresses,
        private readonly ConsentRepository $consents,
        private readonly UserFavoriteGenreRepository $favoriteGenres,
    ) {
    }

    /**
     * Manual address entry (AUTH-20) — never requires device-location consent.
     */
    public function setAddress(
        int $userId,
        City $city,
        string $state,
        ?string $street = null,
        ?string $number = null,
        ?string $complement = null,
    ): void {
        $existing = $this->addresses->findByUserId($userId);

        $this->addresses->save(new UserAddress(
            id: $existing?->id,
            userId: $userId,
            city: $city,
            state: $state,
            source: AddressSource::Manual,
            street: $street,
            number: $number,
            complement: $complement,
            latitude: $existing?->latitude,
            longitude: $existing?->longitude,
            radiusKm: $existing?->radiusKm,
            locationConsentGivenAt: null,
        ));
    }

    /**
     * Device-location consent is distinct from and revocable independently
     * of terms consent (AUTH-21). Revoking falls back the address to manual
     * (or leaves none, if none was ever set) rather than leaving a
     * DeviceLocation-sourced address without a valid consent record.
     */
    public function setDeviceLocationConsent(int $userId, bool $granted, ?string $policyVersion = null): void
    {
        if ($granted) {
            if ($policyVersion === null) {
                throw new InvalidArgumentException('A versão da política de privacidade é obrigatória para conceder consentimento.');
            }

            $this->consents->record(ConsentableType::User, $userId, ConsentType::Location, $policyVersion);

            return;
        }

        $this->consents->revoke(ConsentableType::User, $userId, ConsentType::Location);

        $existing = $this->addresses->findByUserId($userId);

        if ($existing !== null && $existing->source === AddressSource::DeviceLocation) {
            $this->addresses->save(new UserAddress(
                id: $existing->id,
                userId: $userId,
                city: $existing->city,
                state: $existing->state,
                source: AddressSource::Manual,
                street: $existing->street,
                number: $existing->number,
                complement: $existing->complement,
                latitude: $existing->latitude,
                longitude: $existing->longitude,
                radiusKm: $existing->radiusKm,
                locationConsentGivenAt: null,
            ));
        }
    }

    /**
     * @param list<int> $genreIds
     */
    public function setFavoriteGenres(int $userId, array $genreIds): void
    {
        $this->favoriteGenres->replaceForUser($userId, $genreIds);
    }

    public function setSearchRadius(int $userId, int $radiusKm): void
    {
        $existing = $this->addresses->findByUserId($userId);

        if ($existing === null) {
            throw new InvalidArgumentException('É necessário definir um endereço antes de configurar o raio de busca.');
        }

        $this->addresses->save(new UserAddress(
            id: $existing->id,
            userId: $userId,
            city: $existing->city,
            state: $existing->state,
            source: $existing->source,
            street: $existing->street,
            number: $existing->number,
            complement: $existing->complement,
            latitude: $existing->latitude,
            longitude: $existing->longitude,
            radiusKm: $radiusKm,
            locationConsentGivenAt: $existing->locationConsentGivenAt,
        ));
    }
}

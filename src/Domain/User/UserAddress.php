<?php

namespace QOR\App\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\Enum\AddressSource;

final class UserAddress
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly City $city,
        public readonly string $state,
        public readonly AddressSource $source = AddressSource::Manual,
        public readonly ?string $street = null,
        public readonly ?string $number = null,
        public readonly ?string $complement = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?int $radiusKm = null,
        public readonly ?DateTimeImmutable $locationConsentGivenAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('O ID do usuário deve ser válido.');
        }

        if ($this->state === '') {
            throw new InvalidArgumentException('O estado não pode ser vazio.');
        }

        if ($this->source === AddressSource::DeviceLocation && $this->locationConsentGivenAt === null) {
            throw new InvalidArgumentException(
                'O uso da localização do dispositivo exige um consentimento de localização registrado.'
            );
        }
    }
}

<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\User\UserAddress;
use QOR\App\Domain\User\UserAddressRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\UserAddressModel;

class EloquentUserAddressRepository implements UserAddressRepository
{
    public function findByUserId(int $userId): ?UserAddress
    {
        $model = UserAddressModel::where('user_id', $userId)->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function save(UserAddress $address): UserAddress
    {
        $model = UserAddressModel::updateOrCreate(
            ['user_id' => $address->userId],
            [
                'city' => $address->city->value,
                'state' => $address->state,
                'street' => $address->street,
                'number' => $address->number,
                'complement' => $address->complement,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
                'source' => $address->source->value,
                'radius_km' => $address->radiusKm,
                'location_consent_given_at' => $address->locationConsentGivenAt,
            ],
        );

        return $this->toDomain($model);
    }

    private function toDomain(UserAddressModel $model): UserAddress
    {
        // Postgres stores this as a naive `timestamp` column (matching the rest of the
        // schema); Eloquent's 'datetime' cast reinterprets the naive string using
        // config('app.timezone') on read, which silently shifts the instant whenever
        // APP_TIMEZONE isn't UTC. The raw string is always written in UTC wall-clock
        // (asDateTime() preserves the source DateTimeInterface's own timezone on write),
        // so read it back as UTC explicitly rather than trusting the cast.
        /** @var string|null $rawConsentGivenAt */
        $rawConsentGivenAt = $model->getRawOriginal('location_consent_given_at');

        return new UserAddress(
            id: $model->id,
            userId: $model->user_id,
            city: $model->city,
            state: $model->state,
            source: $model->source,
            street: $model->street,
            number: $model->number,
            complement: $model->complement,
            latitude: $model->latitude,
            longitude: $model->longitude,
            radiusKm: $model->radius_km,
            locationConsentGivenAt: $rawConsentGivenAt !== null
                ? new \DateTimeImmutable($rawConsentGivenAt, new \DateTimeZone('UTC'))
                : null,
        );
    }
}

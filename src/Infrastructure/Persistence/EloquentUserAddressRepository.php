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
            locationConsentGivenAt: $model->location_consent_given_at?->toDateTimeImmutable(),
        );
    }
}

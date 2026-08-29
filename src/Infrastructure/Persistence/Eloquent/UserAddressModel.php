<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\UserAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\Enum\AddressSource;

/**
 * @property-read City $city
 * @property-read AddressSource $source
 * @property-read \Illuminate\Support\Carbon|null $location_consent_given_at
 */
class UserAddressModel extends Model
{
    /** @use HasFactory<UserAddressFactory> */
    use HasFactory;

    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'city',
        'state',
        'street',
        'number',
        'complement',
        'latitude',
        'longitude',
        'source',
        'radius_km',
        'location_consent_given_at',
    ];

    protected function casts(): array
    {
        return [
            'city' => City::class,
            'source' => AddressSource::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'location_consent_given_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UserAddressFactory
    {
        return UserAddressFactory::new();
    }
}

<?php

namespace QOR\App\Domain\User;

interface UserAddressRepository
{
    public function findByUserId(int $userId): ?UserAddress;

    /**
     * Creates or updates the fan's single address row (1:1 per ARCHITECTURE.md §4).
     */
    public function save(UserAddress $address): UserAddress;

    /**
     * Every fan address row — used by the scheduled geofenced-notification
     * detectors (NOTIF-01, NOTIF-16) to scan all fans with an address set.
     *
     * @return list<UserAddress>
     */
    public function listAllWithAddress(): array;
}

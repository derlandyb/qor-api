<?php

namespace QOR\App\Domain\User;

interface UserAddressRepository
{
    public function findByUserId(int $userId): ?UserAddress;

    /**
     * Creates or updates the fan's single address row (1:1 per ARCHITECTURE.md §4).
     */
    public function save(UserAddress $address): UserAddress;
}

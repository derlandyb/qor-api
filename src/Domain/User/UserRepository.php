<?php

namespace QOR\App\Domain\User;

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function save(User $user): User;

    /**
     * Soft-deletes the user (LGPD cascade, ARCHITECTURE.md §7).
     */
    public function delete(int $id): void;
}

<?php

namespace Tests\Unit\Domain\User;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $users = [];

    private int $nextId = 1;

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByIds(array $ids): array
    {
        return array_intersect_key($this->users, array_flip($ids));
    }

    public function save(User $user): User
    {
        $id = $user->id ?? $this->nextId++;

        $saved = new User(
            id: $id,
            name: $user->name,
            email: $user->email,
            birthdate: $user->birthdate,
            passwordHash: $user->passwordHash,
            googleId: $user->googleId,
            phone: $user->phone,
            profilePictureUrl: $user->profilePictureUrl,
            emailVerifiedAt: $user->emailVerifiedAt,
        );

        $this->users[$id] = $saved;

        return $saved;
    }

    public function delete(int $id): void
    {
        unset($this->users[$id]);
    }
}

class UserRepositoryContractTest extends TestCase
{
    public function test_GIVEN_a_saved_user_WHEN_finding_by_email_THEN_it_is_found(): void
    {
        $repository = new InMemoryUserRepository();
        $repository->save(new User(
            id: null,
            name: 'Ana',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-01-01'),
            passwordHash: 'hash',
        ));

        $found = $repository->findByEmail('ana@example.com');

        $this->assertNotNull($found);
        $this->assertSame('Ana', $found->name);
    }

    public function test_GIVEN_no_matching_email_WHEN_finding_by_email_THEN_it_returns_null(): void
    {
        $repository = new InMemoryUserRepository();

        $this->assertNull($repository->findByEmail('nobody@example.com'));
    }

    public function test_GIVEN_a_saved_user_WHEN_deleting_THEN_it_is_no_longer_findable(): void
    {
        $repository = new InMemoryUserRepository();
        $saved = $repository->save(new User(
            id: null,
            name: 'Ana',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-01-01'),
            passwordHash: 'hash',
        ));

        $repository->delete($saved->id);

        $this->assertNull($repository->findById($saved->id));
    }
}

<?php

namespace Tests\Unit\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\User\User;

class UserTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_entity_is_valid(): void
    {
        $user = new User(
            id: 1,
            name: 'Ana Silva',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-01-01'),
            passwordHash: 'hashed-password',
        );

        $this->assertSame('ana@example.com', $user->email);
        $this->assertFalse($user->isVerified());
        $this->assertFalse($user->isGoogleOnly());
    }

    public function test_GIVEN_an_invalid_email_format_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User(
            id: null,
            name: 'Ana Silva',
            email: 'not-an-email',
            birthdate: new DateTimeImmutable('1995-01-01'),
            passwordHash: 'hashed-password',
        );
    }

    public function test_GIVEN_an_empty_name_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User(
            id: null,
            name: '',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-01-01'),
            passwordHash: 'hashed-password',
        );
    }

    public function test_GIVEN_neither_password_nor_google_id_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User(
            id: null,
            name: 'Ana Silva',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-01-01'),
        );
    }

    public function test_GIVEN_a_google_id_and_no_password_WHEN_constructing_THEN_it_is_google_only(): void
    {
        $user = new User(
            id: null,
            name: 'Ana Silva',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-01-01'),
            googleId: '123456789',
        );

        $this->assertTrue($user->isGoogleOnly());
    }
}

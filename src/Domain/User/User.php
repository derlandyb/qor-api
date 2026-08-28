<?php

namespace QOR\App\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly DateTimeImmutable $birthdate,
        public readonly ?string $passwordHash = null,
        public readonly ?string $googleId = null,
        public readonly ?string $phone = null,
        public readonly ?string $profilePictureUrl = null,
        public readonly ?DateTimeImmutable $emailVerifiedAt = null,
        public readonly ?string $pendingEmail = null,
    ) {
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("E-mail inválido: {$this->email}");
        }

        if ($this->name === '') {
            throw new InvalidArgumentException('O nome não pode ser vazio.');
        }

        if ($this->passwordHash === null && $this->googleId === null) {
            throw new InvalidArgumentException('O usuário precisa de uma senha ou de uma conta Google vinculada.');
        }
    }

    public function isVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function isGoogleOnly(): bool
    {
        return $this->passwordHash === null && $this->googleId !== null;
    }
}

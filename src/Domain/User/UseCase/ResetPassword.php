<?php

namespace QOR\App\Domain\User\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\User\PasswordResetPort;

final class ResetPassword
{
    public function __construct(
        private readonly PasswordResetPort $passwordReset,
        private readonly PasswordHasher $passwordHasher,
        private readonly PasswordPolicy $passwordPolicy,
    ) {
    }

    /**
     * Always a no-op response shape to the caller regardless of whether
     * $email is registered (AUTH-14, no account enumeration) — the port
     * itself silently skips unknown emails.
     */
    public function requestReset(string $email): void
    {
        $this->passwordReset->sendResetLink($email);
    }

    public function confirmReset(string $email, string $token, string $newPassword): void
    {
        $errors = $this->passwordPolicy->validate($newPassword);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $wasReset = $this->passwordReset->reset($email, $token, $this->passwordHasher->hash($newPassword));

        if (! $wasReset) {
            throw new InvalidArgumentException('Link expirado ou já utilizado');
        }
    }
}

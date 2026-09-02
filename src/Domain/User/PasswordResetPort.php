<?php

namespace QOR\App\Domain\User;

interface PasswordResetPort
{
    /**
     * Consumes $token if valid and unexpired, setting the new password hash.
     * Returns false (without changing anything) for an unknown email or an
     * invalid/expired/already-used token (AUTH-16).
     */
    public function reset(string $email, string $token, string $newPasswordHash): bool;
}

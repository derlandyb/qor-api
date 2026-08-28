<?php

namespace QOR\App\Domain\User;

interface PasswordResetPort
{
    /**
     * Sends a time-limited, single-use reset link if $email is registered.
     * Silent no-op for an unknown email — caller returns the same response
     * either way (AUTH-14, no account enumeration).
     */
    public function sendResetLink(string $email): void;

    /**
     * Consumes $token if valid and unexpired, setting the new password hash.
     * Returns false (without changing anything) for an unknown email or an
     * invalid/expired/already-used token (AUTH-16).
     */
    public function reset(string $email, string $token, string $newPasswordHash): bool;
}

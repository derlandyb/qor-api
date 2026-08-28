<?php

namespace QOR\App\Domain\User;

interface EmailVerificationPort
{
    /**
     * Sends a time-limited verification link to the user's email, unless
     * already verified.
     */
    public function send(int $userId): void;

    /**
     * Marks the user's email verified if $hash matches. Returns true if the
     * email ends up verified (including if it already was); false if $hash
     * doesn't match (link forged/stale beyond the signed URL's own TTL check,
     * which the caller enforces before this is reached).
     */
    public function verify(int $userId, string $hash): bool;
}

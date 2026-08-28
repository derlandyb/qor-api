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

    /**
     * Sends a verification link to the user's pending (not-yet-active) new
     * email address (AUTH-19). No-op if there's no pending email.
     */
    public function sendForPendingEmail(int $userId): void;

    /**
     * Promotes the pending email to the active one if $hash matches, clearing
     * the pending slot and marking the (now active) email verified. Returns
     * false if there's no pending email or $hash doesn't match.
     */
    public function confirmPendingEmail(int $userId, string $hash): bool;
}

<?php

namespace QOR\App\Domain\User;

interface OtpVerificationPort
{
    /**
     * Generates and emails a time-limited OTP code to verify $email, unless
     * the account is unknown or already verified (silent no-op either way —
     * same anti-enumeration rule as EmailVerificationPort::send()).
     */
    public function issueEmailVerificationCode(string $email): void;

    /**
     * Marks the account verified if $code matches the most recently issued,
     * unexpired code for $email and the attempt limit hasn't been exceeded.
     * Wrong codes count against the attempt limit; a match consumes the code.
     */
    public function verifyEmailCode(string $email, string $code): bool;

    /**
     * Generates and emails a time-limited OTP code to reset the password for
     * $email. Silent no-op for an unknown email (AUTH-14, no enumeration).
     */
    public function issuePasswordResetCode(string $email): void;

    /**
     * Verifies $code for $email and, on success, returns a real
     * Password::broker('fans') reset token — consumable by the existing
     * password-reset-confirm endpoint unchanged. Returns null on a wrong,
     * expired, or attempt-exhausted code.
     */
    public function verifyPasswordResetCode(string $email, string $code): ?string;
}

<?php

namespace QOR\App\Infrastructure\Auth;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use QOR\App\Domain\User\OtpVerificationPort;
use QOR\App\Infrastructure\Notifications\OtpCodeNotification;
use QOR\App\Infrastructure\Persistence\Eloquent\OtpCodeModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class OtpAdapter implements OtpVerificationPort
{
    private const PURPOSE_EMAIL_VERIFICATION = 'email_verification';
    private const PURPOSE_PASSWORD_RESET = 'password_reset';

    public function issueEmailVerificationCode(string $email): void
    {
        $user = UserModel::where('email', $email)->first();

        if ($user === null || $user->hasVerifiedEmail()) {
            return;
        }

        $this->issue(self::PURPOSE_EMAIL_VERIFICATION, $email);
    }

    public function verifyEmailCode(string $email, string $code): bool
    {
        if (! $this->consume(self::PURPOSE_EMAIL_VERIFICATION, $email, $code)) {
            return false;
        }

        $user = UserModel::where('email', $email)->first();

        return $user !== null && (bool) $user->markEmailAsVerified();
    }

    public function issuePasswordResetCode(string $email): void
    {
        if (UserModel::where('email', $email)->doesntExist()) {
            return;
        }

        $this->issue(self::PURPOSE_PASSWORD_RESET, $email);
    }

    public function verifyPasswordResetCode(string $email, string $code): ?string
    {
        if (! $this->consume(self::PURPOSE_PASSWORD_RESET, $email, $code)) {
            return null;
        }

        $user = UserModel::where('email', $email)->first();
        if ($user === null) {
            return null;
        }

        return Password::broker('fans')->createToken($user);
    }

    private function issue(string $purpose, string $identifier): void
    {
        /** @var int $length */
        $length = config('qor.auth.otp_length');
        /** @var int $ttlMinutes */
        $ttlMinutes = config('qor.auth.otp_ttl_minutes');

        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        OtpCodeModel::where('purpose', $purpose)->where('identifier', $identifier)->delete();

        OtpCodeModel::create([
            'purpose' => $purpose,
            'identifier' => $identifier,
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        Notification::route('mail', $identifier)->notify(new OtpCodeNotification($code, $purpose));
    }

    private function consume(string $purpose, string $identifier, string $code): bool
    {
        /** @var int $maxAttempts */
        $maxAttempts = config('qor.auth.otp_max_attempts');

        $row = OtpCodeModel::where('purpose', $purpose)->where('identifier', $identifier)->first();

        if ($row === null || $row->expires_at->isPast() || $row->attempts >= $maxAttempts) {
            return false;
        }

        if (! hash_equals($row->code_hash, hash('sha256', $code))) {
            $row->increment('attempts');

            return false;
        }

        $row->delete();

        return true;
    }
}

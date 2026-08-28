<?php

namespace QOR\App\Infrastructure\Auth;

use Illuminate\Support\Facades\Password;
use QOR\App\Domain\User\PasswordResetPort;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class LaravelPasswordResetAdapter implements PasswordResetPort
{
    public function sendResetLink(string $email): void
    {
        Password::broker('fans')->sendResetLink(['email' => $email]);
    }

    public function reset(string $email, string $token, string $newPasswordHash): bool
    {
        $status = Password::broker('fans')->reset(
            ['email' => $email, 'token' => $token, 'password' => $newPasswordHash],
            function (UserModel $user, string $passwordHash): void {
                $user->forceFill(['password_hash' => $passwordHash])->save();
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}

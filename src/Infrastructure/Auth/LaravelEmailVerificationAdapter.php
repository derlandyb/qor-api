<?php

namespace QOR\App\Infrastructure\Auth;

use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class LaravelEmailVerificationAdapter implements EmailVerificationPort
{
    public function send(int $userId): void
    {
        $model = UserModel::findOrFail($userId);

        if (! $model->hasVerifiedEmail()) {
            $model->sendEmailVerificationNotification();
        }
    }

    public function verify(int $userId, string $hash): bool
    {
        $model = UserModel::findOrFail($userId);

        if ($model->hasVerifiedEmail()) {
            return true;
        }

        if (! hash_equals(sha1($model->getEmailForVerification()), $hash)) {
            return false;
        }

        return (bool) $model->markEmailAsVerified();
    }
}

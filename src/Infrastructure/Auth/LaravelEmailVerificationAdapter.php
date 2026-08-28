<?php

namespace QOR\App\Infrastructure\Auth;

use Illuminate\Support\Facades\Notification;
use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Infrastructure\Notifications\PendingEmailVerificationNotification;
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

    public function sendForPendingEmail(int $userId): void
    {
        $model = UserModel::findOrFail($userId);

        if ($model->pending_email === null) {
            return;
        }

        Notification::route('mail', $model->pending_email)
            ->notify(new PendingEmailVerificationNotification($model->pending_email));
    }

    public function confirmPendingEmail(int $userId, string $hash): bool
    {
        $model = UserModel::findOrFail($userId);

        if ($model->pending_email === null || ! hash_equals(sha1($model->pending_email), $hash)) {
            return false;
        }

        $model->forceFill([
            'email' => $model->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        return true;
    }
}

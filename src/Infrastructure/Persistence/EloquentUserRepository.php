<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class EloquentUserRepository implements UserRepository
{
    public function findByEmail(string $email): ?User
    {
        $model = UserModel::where('email', $email)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findById(int $id): ?User
    {
        $model = UserModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(User $user): User
    {
        $model = $user->id !== null ? UserModel::findOrFail($user->id) : new UserModel();

        $model->fill([
            'name' => $user->name,
            'email' => $user->email,
            'pending_email' => $user->pendingEmail,
            'password_hash' => $user->passwordHash,
            'google_id' => $user->googleId,
            'phone' => $user->phone,
            'profile_picture_url' => $user->profilePictureUrl,
            'birthdate' => $user->birthdate,
        ]);

        if ($user->emailVerifiedAt !== null) {
            $model->email_verified_at = \Illuminate\Support\Carbon::instance($user->emailVerifiedAt);
        }

        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        UserModel::destroy($id);
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            birthdate: $model->birthdate->toDateTimeImmutable(),
            passwordHash: $model->password_hash,
            googleId: $model->google_id,
            phone: $model->phone,
            profilePictureUrl: $model->profile_picture_url,
            emailVerifiedAt: $model->email_verified_at?->toDateTimeImmutable(),
            pendingEmail: $model->pending_email,
        );
    }
}

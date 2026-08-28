<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;

class EloquentAdminAccountRepository implements AdminAccountRepository
{
    public function findByEmail(string $email): ?AdminAccount
    {
        $model = AdminUserModel::where('email', $email)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(AdminAccount $account): AdminAccount
    {
        $model = $account->id !== null ? AdminUserModel::findOrFail($account->id) : new AdminUserModel();

        $model->fill([
            'name' => $account->name,
            'email' => $account->email,
            'password' => $account->passwordHash,
        ]);

        if ($account->id === null) {
            $model->is_super_admin = $account->isSuperAdmin;
        }

        $model->save();

        return $this->toDomain($model);
    }

    private function toDomain(AdminUserModel $model): AdminAccount
    {
        return new AdminAccount(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            passwordHash: $model->password,
            isSuperAdmin: (bool) $model->is_super_admin,
        );
    }
}

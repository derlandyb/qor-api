<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;

/**
 * A known-credential local Super Admin — until this seeder existed, there
 * was no way to reach the Super Admin approval/plan queues in local dev
 * without raw DB access. `updateOrCreate` keeps it idempotent across
 * repeated `make up`/`db:seed` runs (per ARCHITECTURE.md §8.7).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $admin = AdminUserModel::query()->updateOrCreate(
            ['email' => 'superadmin@qor.dev'],
            [
                'name' => 'QOR Super Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        // is_super_admin is deliberately absent from $fillable (privilege
        // escalation risk via mass-assignment) — forceFill it explicitly,
        // same pattern AdminUserFactory::superAdmin() already uses.
        $admin->forceFill(['is_super_admin' => true])->save();
    }
}

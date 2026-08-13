<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the platform's first Super Admin account, idempotent by email.
     *
     * Collision behavior (context.md, Agent's Discretion): if the configured email already
     * belongs to a non-Super-Admin row, skip and log a warning — never promote or overwrite
     * an unrelated account.
     */
    public function run(): void
    {
        $email = config('admin.super_admin_email');
        $password = config('admin.super_admin_initial_password');

        if (blank($email) || blank($password)) {
            Log::warning('SuperAdminSeeder: SUPER_ADMIN_EMAIL/SUPER_ADMIN_INITIAL_PASSWORD not configured — skipping.');

            return;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->role !== Role::SuperAdmin) {
                Log::warning("SuperAdminSeeder: configured email {$email} already belongs to a non-super-admin account (id={$existing->id}) — skipping, not overwriting.");
            }

            return;
        }

        User::query()->create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => $password,
            'role' => Role::SuperAdmin,
            'must_change_password' => true,
        ]);
    }
}

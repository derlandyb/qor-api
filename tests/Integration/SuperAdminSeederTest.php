<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

it('given the seeder runs against real Postgres then the super admin row exists and the super-admin gate resolves for it via a real request', function () {
    Config::set('admin.super_admin_email', 'super-integration@qualorock.example');
    Config::set('admin.super_admin_initial_password', 'InitialPass1');

    (new SuperAdminSeeder)->run();

    $superAdmin = User::query()->where('email', 'super-integration@qualorock.example')->first();
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->role)->toBe(Role::SuperAdmin)
        ->and($superAdmin->must_change_password)->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('super-admin'))->toBeTrue();

    Sanctum::actingAs($superAdmin);
    $this->getJson('/api/user')->assertOk()->assertJsonPath('email', 'super-integration@qualorock.example');
});

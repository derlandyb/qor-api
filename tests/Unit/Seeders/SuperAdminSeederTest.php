<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Config::set('admin.super_admin_email', 'super@qualorock.example');
    Config::set('admin.super_admin_initial_password', 'InitialPass1');
});

it('given the seeder runs twice then only one super admin row exists', function () {
    (new SuperAdminSeeder)->run();
    (new SuperAdminSeeder)->run();

    expect(User::query()->where('email', 'super@qualorock.example')->count())->toBe(1);

    $superAdmin = User::query()->where('email', 'super@qualorock.example')->first();
    expect($superAdmin->role)->toBe(Role::SuperAdmin)
        ->and($superAdmin->must_change_password)->toBeTrue();
});

it('given a colliding email owned by a non-super-admin then the seeder skips without overwriting', function () {
    Log::shouldReceive('warning')->once();

    $existing = User::factory()->create([
        'email' => 'super@qualorock.example',
        'role' => Role::User,
    ]);

    (new SuperAdminSeeder)->run();

    $reloaded = $existing->fresh();
    expect($reloaded->role)->toBe(Role::User)
        ->and($reloaded->id)->toBe($existing->id)
        ->and(User::query()->where('email', 'super@qualorock.example')->count())->toBe(1);
});

<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('given a user with the default role when isAdmin is checked then it is false', function () {
    // ->create()->fresh() — `role`'s `user` default is applied by the database at insert time
    // (the migration's ->default('user')); Eloquent doesn't pull a DB-applied default back onto
    // the in-memory instance that triggered the insert, so a re-fetch is needed to observe it.
    $user = User::factory()->create()->fresh();

    expect($user->role)->toBe(Role::User)
        ->and($user->isAdmin())->toBeFalse();
});

it('given a user with the admin role when isAdmin is checked then it is true', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();
});

it('given the admin gate when evaluated for both role values then it matches isAdmin exactly', function () {
    $admin = User::factory()->admin()->create();
    $standard = User::factory()->create();

    expect(Gate::forUser($admin)->allows('admin'))->toBeTrue()
        ->and(Gate::forUser($standard)->allows('admin'))->toBeFalse();
});

it('given all three roles then isAdmin is true only for admin and super_admin', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($user->isAdmin())->toBeFalse()
        ->and($admin->isAdmin())->toBeTrue()
        ->and($superAdmin->isAdmin())->toBeTrue();
});

it('given the super-admin gate when evaluated for all three roles then it matches only super_admin', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect(Gate::forUser($user)->allows('super-admin'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('super-admin'))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('super-admin'))->toBeTrue();
});

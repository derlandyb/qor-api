<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('given a regular admin when creating a staff account then it is forbidden with 403', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/admin/staff', [
        'name' => 'Nova Moderadora',
        'email' => 'nova-moderadora-403@example.com',
    ])->assertForbidden();
});

it('given a regular admin when listing staff accounts then it is forbidden with 403', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson('/api/admin/staff')->assertForbidden();
});

it('given a super admin when listing staff accounts then admin and super admin users are returned but regular users are not', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    User::factory()->create(); // regular user — excluded
    Sanctum::actingAs($superAdmin);

    $response = $this->getJson('/api/admin/staff')->assertOk();

    $emails = collect($response->json('data'))->pluck('email')->all();
    expect($emails)->toContain($superAdmin->email)
        ->and($emails)->toContain($admin->email)
        ->and(count($emails))->toBe(2);
});

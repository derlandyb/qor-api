<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
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

it('given a regular admin when creating a staff account then the 403 message is in pt-BR, not the default English text', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/admin/staff', [
        'name' => 'Nova Moderadora',
        'email' => 'nova-moderadora-ptbr@example.com',
    ])->assertForbidden()
        ->assertJsonPath('message', 'Você não tem permissão para executar esta ação.');
});

it('given an already-registered email when creating a staff account then it returns a pt-BR validation error', function () {
    User::factory()->create(['email' => 'ja-existe@example.com']);
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->postJson('/api/admin/staff', [
        'name' => 'Nova Moderadora',
        'email' => 'ja-existe@example.com',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Este e-mail já está cadastrado.');
});

it('given the credentials notification fails to send when creating a staff account then the user row is rolled back', function () {
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('mail transport unreachable'));
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->postJson('/api/admin/staff', [
        'name' => 'Nova Moderadora',
        'email' => 'orphan-check@example.com',
    ])->assertStatus(500);

    expect(User::query()->where('email', 'orphan-check@example.com')->exists())->toBeFalse();
});

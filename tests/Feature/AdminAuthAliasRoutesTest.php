<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

it('given the same credentials when posted to /api/register and /api/admin/register then both return identical shape and status', function () {
    $legacyPayload = [
        'name' => 'Jane Legacy',
        'email' => 'jane-legacy-register@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
    $adminPayload = [
        'name' => 'Jane Admin',
        'email' => 'jane-admin-register@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $legacyResponse = $this->postJson('/api/register', $legacyPayload);
    $adminResponse = $this->postJson('/api/admin/register', $adminPayload);

    expect($adminResponse->status())->toBe($legacyResponse->status())
        ->and(array_keys($adminResponse->json()))->toBe(array_keys($legacyResponse->json()))
        ->and(array_keys($adminResponse->json('user')))->toBe(array_keys($legacyResponse->json('user')));
});

it('given the same credentials when posted to /api/login and /api/admin/login then both return identical shape and status', function () {
    User::factory()->create(['email' => 'jane-legacy-login@example.com', 'password' => 'password123']);
    User::factory()->create(['email' => 'jane-admin-login@example.com', 'password' => 'password123']);

    $legacyResponse = $this->postJson('/api/login', ['email' => 'jane-legacy-login@example.com', 'password' => 'password123']);
    $adminResponse = $this->postJson('/api/admin/login', ['email' => 'jane-admin-login@example.com', 'password' => 'password123']);

    expect($adminResponse->status())->toBe($legacyResponse->status())
        ->and(array_keys($adminResponse->json()))->toBe(array_keys($legacyResponse->json()))
        ->and(array_keys($adminResponse->json('user')))->toBe(array_keys($legacyResponse->json('user')));
});

it('given a valid token when posted to /api/logout and /api/admin/logout then both return identical shape and status', function () {
    $legacyUser = User::factory()->create();
    $adminUser = User::factory()->create();
    $legacyToken = $legacyUser->createToken('api', ['*'], now()->addDays(30));
    $adminToken = $adminUser->createToken('api', ['*'], now()->addDays(30));

    $legacyResponse = $this->withHeader('Authorization', "Bearer {$legacyToken->plainTextToken}")->postJson('/api/logout');
    $adminResponse = $this->withHeader('Authorization', "Bearer {$adminToken->plainTextToken}")->postJson('/api/admin/logout');

    expect($adminResponse->status())->toBe($legacyResponse->status())
        ->and($adminResponse->status())->toBe(204);
});

it('given the same email when posted to /api/password/forgot and /api/admin/password/forgot then both return identical shape and status', function () {
    User::factory()->create(['email' => 'jane-forgot@example.com']);

    $legacyResponse = $this->postJson('/api/password/forgot', ['email' => 'jane-forgot@example.com']);
    $adminResponse = $this->postJson('/api/admin/password/forgot', ['email' => 'jane-forgot@example.com']);

    expect($adminResponse->status())->toBe($legacyResponse->status())
        ->and($adminResponse->json())->toBe($legacyResponse->json());
});

it('given a valid reset token when posted to /api/password/reset and /api/admin/password/reset then both return identical shape and status', function () {
    $legacyUser = User::factory()->create(['email' => 'jane-legacy-reset@example.com', 'password' => 'old-password1']);
    $adminUser = User::factory()->create(['email' => 'jane-admin-reset@example.com', 'password' => 'old-password1']);
    $legacyToken = Password::createToken($legacyUser);
    $adminToken = Password::createToken($adminUser);

    $legacyResponse = $this->postJson('/api/password/reset', [
        'token' => $legacyToken,
        'email' => 'jane-legacy-reset@example.com',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ]);
    $adminResponse = $this->postJson('/api/admin/password/reset', [
        'token' => $adminToken,
        'email' => 'jane-admin-reset@example.com',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ]);

    expect($adminResponse->status())->toBe($legacyResponse->status())
        ->and($adminResponse->json())->toBe($legacyResponse->json());
});

it('given a freshly seeded super admin when logging in then mustChangePassword is true', function () {
    $superAdmin = User::factory()->superAdmin()->create(['password' => 'password123', 'must_change_password' => true]);

    $this->postJson('/api/admin/login', ['email' => $superAdmin->email, 'password' => 'password123'])
        ->assertOk()
        ->assertJsonPath('mustChangePassword', true);
});

it('given a regular verified user when logging in then mustChangePassword is false', function () {
    $user = User::factory()->create(['password' => 'password123', 'must_change_password' => false]);

    $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'password123'])
        ->assertOk()
        ->assertJsonPath('mustChangePassword', false);
});

it('given the legacy /api/login route then mustChangePassword is additive and does not break the existing response', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
        ->assertOk()
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token', 'mustChangePassword']);
});

it('given an authenticated user when GET /api/admin/me is called then it returns the same user/mustChangePassword shape as login', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $token = $user->createToken('api', ['*'], now()->addDays(30));

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->getJson('/api/admin/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('mustChangePassword', true);
});

it('given no token when GET /api/admin/me is called then it returns unauthorized', function () {
    $this->getJson('/api/admin/me')->assertUnauthorized();
});

it('given the same submission when posted to /api/verification/applications and /api/admin/verification/applications then both return identical shape and status', function () {
    $legacyUser = User::factory()->create();
    $adminUser = User::factory()->create();
    $payload = [
        'business_name' => 'Rock in Rio Produções',
        'account_type' => 'promoter',
        'contact_email' => 'contato@rockinrio.example',
        'contact_phone' => '27999998888',
        'social_link' => 'https://instagram.com/rockinrioproducoes',
    ];

    $legacyResponse = $this->actingAs($legacyUser)->postJson('/api/verification/applications', $payload);
    $adminResponse = $this->actingAs($adminUser)->postJson('/api/admin/verification/applications', $payload);

    expect($adminResponse->status())->toBe($legacyResponse->status())
        ->and(array_keys($adminResponse->json('data')))->toBe(array_keys($legacyResponse->json('data')));
});

it('given the same account when GET /api/verification/status and /api/admin/verification/status are called then both return identical shape and status', function () {
    $user = User::factory()->create();

    $legacyResponse = $this->actingAs($user)->getJson('/api/verification/status');
    $adminResponse = $this->actingAs($user)->getJson('/api/admin/verification/status');

    expect($adminResponse->status())->toBe($legacyResponse->status())
        ->and($adminResponse->json())->toBe($legacyResponse->json());
});

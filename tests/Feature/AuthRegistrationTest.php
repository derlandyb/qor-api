<?php

use App\Models\User;

it('given a new visitor when registering then a Sanctum token and account are returned', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Rocker',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('given an already registered email when registering then it returns a validation error', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Jane Rocker',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('given a registered account when logging in with the correct password then a token is returned', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
});

it('given a registered account when logging in with the wrong password then it returns an unauthorized error', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'As credenciais informadas estão incorretas.');
});

it('given an authenticated user when logging out then their current token is revoked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api', ['*'], now()->addDays(30));

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

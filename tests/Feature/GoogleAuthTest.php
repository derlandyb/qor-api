<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('given a Google identity when signing in then an account is linked or created', function () {
    $socialiteUser = SocialiteUser::fake([
        'id' => 'google-123',
        'email' => 'jane@example.com',
        'name' => 'Jane Rocker',
    ]);

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('valid-id-token')
        ->andReturn($socialiteUser);

    $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-id-token']);

    $response->assertOk()
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    $user = User::query()->where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('google-123')
        ->and($user->password)->toBeNull();
});

it('given an existing password account when signing in with a matching Google email then it links the account', function () {
    $existing = User::factory()->create(['email' => 'jane@example.com']);

    $socialiteUser = SocialiteUser::fake([
        'id' => 'google-456',
        'email' => 'jane@example.com',
        'name' => 'Jane Rocker',
    ]);

    Socialite::shouldReceive('driver->stateless->userFromToken')->once()->andReturn($socialiteUser);

    $this->postJson('/api/auth/google', ['id_token' => 'valid-id-token'])
        ->assertOk()
        ->assertJsonPath('user.id', $existing->id);

    expect($existing->fresh()->google_id)->toBe('google-456');
});

it('given an already-linked Google account when signing in again then it signs in without error', function () {
    $existing = User::factory()->create([
        'email' => 'jane@example.com',
        'google_id' => 'google-789',
    ]);

    $socialiteUser = SocialiteUser::fake([
        'id' => 'google-789',
        'email' => 'jane@example.com',
        'name' => 'Jane Rocker',
    ]);

    Socialite::shouldReceive('driver->stateless->userFromToken')->once()->andReturn($socialiteUser);

    $this->postJson('/api/auth/google', ['id_token' => 'valid-id-token'])
        ->assertOk()
        ->assertJsonPath('user.id', $existing->id);
});

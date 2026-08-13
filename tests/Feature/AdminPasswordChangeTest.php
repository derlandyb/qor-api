<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('given the wrong current password when changing password then it is rejected with 422', function () {
    $user = User::factory()->create(['password' => 'old-password1', 'must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/change-password', [
        'current_password' => 'not-the-current-password',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('given a correct current password and a valid new password when changing then must_change_password clears and a follow-up login shows mustChangePassword false', function () {
    $user = User::factory()->create(['email' => 'jane-change@example.com', 'password' => 'old-password1', 'must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/change-password', [
        'current_password' => 'old-password1',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])->assertOk();

    expect($user->fresh()->must_change_password)->toBeFalse();

    $this->postJson('/api/admin/login', [
        'email' => 'jane-change@example.com',
        'password' => 'new-password1',
    ])->assertOk()->assertJsonPath('mustChangePassword', false);
});

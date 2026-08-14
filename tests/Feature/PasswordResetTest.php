<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('given a registered email when forgot-password is requested then it returns a neutral success message', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/password/forgot', ['email' => 'jane@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'Se este e-mail estiver cadastrado, enviaremos um link de redefinição de senha.');
});

it('given an unknown email when forgot-password is requested then it still returns the neutral success message', function () {
    $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'Se este e-mail estiver cadastrado, enviaremos um link de redefinição de senha.');
});

it('given a valid reset token when the password is reset then it succeeds and the new password can be used to log in', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'old-password1']);
    $token = Password::createToken($user);

    $this->postJson('/api/password/reset', [
        'token' => $token,
        'email' => 'jane@example.com',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])->assertOk();

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'new-password1',
    ])->assertOk();
});

it('given an invalid reset token when the password is reset then it returns a validation error', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/password/reset', [
        'token' => 'not-a-real-token',
        'email' => 'jane@example.com',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])->assertUnprocessable();
});

it('given a forgot-password request when the reset email is sent then the link points at the frontend app, not this API', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'jane@example.com']);
    config(['app.frontend_url' => 'http://localhost:5173']);

    $this->postJson('/api/password/forgot', ['email' => 'jane@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'http://localhost:5173/reset-password?token=')
            && str_contains($url, 'email=jane%40example.com');
    });
});

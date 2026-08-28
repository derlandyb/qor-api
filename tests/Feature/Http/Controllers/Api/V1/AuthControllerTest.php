<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_valid_fields_WHEN_registering_THEN_it_creates_an_unverified_account_and_sends_a_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'Senha123',
            'birthdate' => '1995-05-10',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.email', 'ana@example.com');
        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
        $this->assertDatabaseHas('consent_records', ['consentable_type' => 'user', 'consent_type' => 'terms']);

        $user = UserModel::where('email', 'ana@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_GIVEN_a_duplicate_email_WHEN_registering_THEN_it_returns_422(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'Senha123',
            'birthdate' => '1995-05-10',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Este e-mail já está cadastrado');
    }

    public function test_GIVEN_terms_not_accepted_WHEN_registering_THEN_it_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'Senha123',
            'birthdate' => '1995-05-10',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['terms_accepted']]);
    }

    public function test_GIVEN_correct_credentials_for_a_verified_account_WHEN_logging_in_THEN_it_returns_a_token(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com', 'password_hash' => Hash::make('Senha123')]);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'Senha123']);

        $response->assertStatus(200)->assertJsonStructure(['data', 'token']);
    }

    public function test_GIVEN_the_wrong_password_WHEN_logging_in_THEN_it_returns_401_with_a_generic_message(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com', 'password_hash' => Hash::make('Senha123')]);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'errada']);

        $response->assertStatus(401)->assertJsonPath('message', 'Credenciais inválidas');
    }

    public function test_GIVEN_an_unverified_account_WHEN_logging_in_with_correct_credentials_THEN_it_returns_403(): void
    {
        UserModel::factory()->unverified()->create(['email' => 'ana@example.com', 'password_hash' => Hash::make('Senha123')]);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'Senha123']);

        $response->assertStatus(403)->assertJsonPath('message', 'Confirme seu e-mail para continuar');
    }

    public function test_GIVEN_a_new_google_email_WHEN_authenticating_THEN_it_creates_a_pre_verified_account(): void
    {
        $response = $this->postJson('/api/v1/auth/google', [
            'google_id' => '123456789',
            'email' => 'nova@example.com',
            'name' => 'Nova Conta',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.email_verified', true);
        $this->assertDatabaseHas('users', ['email' => 'nova@example.com', 'google_id' => '123456789']);
    }

    public function test_GIVEN_an_existing_google_email_WHEN_authenticating_THEN_it_logs_into_the_existing_account_without_duplicating(): void
    {
        UserModel::factory()->google()->create(['email' => 'existente@example.com', 'google_id' => '111']);

        $response = $this->postJson('/api/v1/auth/google', [
            'google_id' => '111',
            'email' => 'existente@example.com',
            'name' => 'Existente',
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, UserModel::where('email', 'existente@example.com')->count());
    }

    public function test_GIVEN_an_authenticated_fan_WHEN_logging_out_THEN_the_token_is_revoked(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_GIVEN_a_registered_email_WHEN_requesting_a_password_reset_THEN_it_returns_a_generic_success_message(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com']);

        $response = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ana@example.com']);

        $response->assertStatus(200)->assertJsonPath('message', 'Se este e-mail existir, você receberá um link.');
    }

    public function test_GIVEN_an_unknown_email_WHEN_requesting_a_password_reset_THEN_it_returns_the_same_generic_message(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ninguem@example.com']);

        $response->assertStatus(200)->assertJsonPath('message', 'Se este e-mail existir, você receberá um link.');
    }

    public function test_GIVEN_a_valid_reset_token_WHEN_confirming_a_new_password_THEN_it_updates_the_password(): void
    {
        $user = UserModel::factory()->create(['email' => 'ana@example.com']);
        $token = Password::broker('fans')->createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'ana@example.com',
            'token' => $token,
            'password' => 'NovaSenha123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('NovaSenha123', $user->fresh()->password_hash));
    }

    public function test_GIVEN_an_invalid_reset_token_WHEN_confirming_a_new_password_THEN_it_returns_422(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'ana@example.com',
            'token' => 'token-invalido',
            'password' => 'NovaSenha123',
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Link expirado ou já utilizado');
    }

    public function test_GIVEN_a_valid_signed_link_WHEN_verifying_email_THEN_the_account_becomes_verified(): void
    {
        $user = UserModel::factory()->unverified()->create();

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->getJson($url);

        $response->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_GIVEN_an_unverified_account_WHEN_resending_verification_THEN_it_returns_the_generic_message(): void
    {
        UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);

        $response = $this->postJson('/api/v1/auth/email/verification-notification', ['email' => 'ana@example.com']);

        $response->assertStatus(200);
    }
}

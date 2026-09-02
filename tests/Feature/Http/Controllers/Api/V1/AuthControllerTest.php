<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use QOR\App\Infrastructure\Notifications\OtpCodeNotification;
use QOR\App\Infrastructure\Persistence\Eloquent\OtpCodeModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_valid_fields_WHEN_registering_THEN_it_creates_an_unverified_account_and_sends_an_otp_code(): void
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
        $this->assertDatabaseHas('otp_codes', ['purpose' => 'email_verification', 'identifier' => 'ana@example.com']);

        Notification::assertSentOnDemand(OtpCodeNotification::class);
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

        $response->assertStatus(200)->assertJsonPath('message', 'Se este e-mail existir, você receberá um código.');
    }

    public function test_GIVEN_an_unknown_email_WHEN_requesting_a_password_reset_THEN_it_returns_the_same_generic_message(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ninguem@example.com']);

        $response->assertStatus(200)->assertJsonPath('message', 'Se este e-mail existir, você receberá um código.');
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

    public function test_GIVEN_a_freshly_registered_account_WHEN_verifying_with_the_correct_otp_code_THEN_the_account_becomes_verified(): void
    {
        $user = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        $code = $this->issueAndCaptureOtpCode('ana@example.com', '/api/v1/auth/email/verification-notification');

        $response = $this->postJson('/api/v1/auth/email/verify-code', [
            'email' => 'ana@example.com',
            'code' => $code,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('otp_codes', ['purpose' => 'email_verification', 'identifier' => 'ana@example.com']);
    }

    public function test_GIVEN_the_wrong_otp_code_WHEN_verifying_email_THEN_it_returns_422_and_the_account_stays_unverified(): void
    {
        $user = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        $this->issueAndCaptureOtpCode('ana@example.com', '/api/v1/auth/email/verification-notification');

        $response = $this->postJson('/api/v1/auth/email/verify-code', [
            'email' => 'ana@example.com',
            'code' => '000000',
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Código inválido ou expirado.');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_GIVEN_a_code_that_has_failed_the_maximum_attempts_WHEN_verifying_email_THEN_it_is_locked_out(): void
    {
        UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        // Seeded directly (not via the resend endpoint) so this test's 5
        // wrong-code POSTs don't also compete with qor-auth's own 5-per-
        // minute-per-IP throttle for budget.
        OtpCodeModel::create([
            'purpose' => 'email_verification',
            'identifier' => 'ana@example.com',
            'code_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/email/verify-code', ['email' => 'ana@example.com', 'code' => '000000']);
        }

        $row = OtpCodeModel::where('purpose', 'email_verification')->where('identifier', 'ana@example.com')->first();
        $this->assertNotNull($row);
        $this->assertSame(5, $row->attempts);
    }

    public function test_GIVEN_an_expired_otp_code_WHEN_verifying_email_THEN_it_returns_422(): void
    {
        $user = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        OtpCodeModel::create([
            'purpose' => 'email_verification',
            'identifier' => 'ana@example.com',
            'code_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/auth/email/verify-code', [
            'email' => 'ana@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Código inválido ou expirado.');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_GIVEN_a_registered_email_WHEN_requesting_a_password_reset_code_and_verifying_it_THEN_it_returns_a_usable_reset_token(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com']);
        $code = $this->issueAndCaptureOtpCode('ana@example.com', '/api/v1/auth/password/forgot');

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'ana@example.com',
            'code' => $code,
        ]);

        $response->assertStatus(200)->assertJsonStructure(['data' => ['token']]);

        $resetResponse = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'ana@example.com',
            'token' => $response->json('data.token'),
            'password' => 'NovaSenha123',
        ]);
        $resetResponse->assertStatus(200);
    }

    public function test_GIVEN_the_wrong_password_reset_code_WHEN_verifying_THEN_it_returns_422(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com']);
        $this->issueAndCaptureOtpCode('ana@example.com', '/api/v1/auth/password/forgot');

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'ana@example.com',
            'code' => '000000',
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Código inválido ou expirado.');
    }

    private function issueAndCaptureOtpCode(string $email, ?string $issueRoute = null): string
    {
        Notification::fake();

        if ($issueRoute !== null) {
            $this->postJson($issueRoute, ['email' => $email]);
        }

        $code = null;
        Notification::assertSentOnDemand(
            OtpCodeNotification::class,
            function (OtpCodeNotification $notification) use (&$code): bool {
                $code = $notification->getCode();

                return true;
            },
        );

        Notification::fake(); // stop faking so the next real request re-issues cleanly if needed

        /** @var string $code */
        return $code;
    }
}

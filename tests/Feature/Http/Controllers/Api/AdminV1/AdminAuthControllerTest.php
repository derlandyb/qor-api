<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use Tests\TestCase;

class AdminAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function venueAdmin(string $email, ApprovalStatus $approvalStatus): AdminUserModel
    {
        $admin = AdminUserModel::factory()->create(['email' => $email, 'password' => Hash::make('Senha123')]);

        VenueModel::factory()->create([
            'venue_admin_user_id' => $admin->id,
            'approval_status' => $approvalStatus->value,
        ]);

        return $admin;
    }

    public function test_GIVEN_correct_credentials_for_a_pending_approval_account_WHEN_logging_in_THEN_it_returns_a_token(): void
    {
        $this->venueAdmin('venue@example.com', ApprovalStatus::PendingApproval);

        $response = $this->postJson('/api/admin/v1/auth/login', ['email' => 'venue@example.com', 'password' => 'Senha123']);

        $response->assertStatus(200)->assertJsonStructure(['data', 'token']);
    }

    public function test_GIVEN_correct_credentials_for_an_approved_account_WHEN_logging_in_THEN_it_returns_a_token(): void
    {
        $this->venueAdmin('aprovado@example.com', ApprovalStatus::Approved);

        $response = $this->postJson('/api/admin/v1/auth/login', ['email' => 'aprovado@example.com', 'password' => 'Senha123']);

        $response->assertStatus(200)->assertJsonStructure(['data', 'token']);
    }

    public function test_GIVEN_correct_credentials_for_a_rejected_or_suspended_account_WHEN_logging_in_THEN_it_returns_a_token(): void
    {
        $this->venueAdmin('rejeitado@example.com', ApprovalStatus::Rejected);
        $this->venueAdmin('suspenso@example.com', ApprovalStatus::Suspended);

        $rejectedResponse = $this->postJson('/api/admin/v1/auth/login', ['email' => 'rejeitado@example.com', 'password' => 'Senha123']);
        $suspendedResponse = $this->postJson('/api/admin/v1/auth/login', ['email' => 'suspenso@example.com', 'password' => 'Senha123']);

        $rejectedResponse->assertStatus(200)->assertJsonStructure(['data', 'token']);
        $suspendedResponse->assertStatus(200)->assertJsonStructure(['data', 'token']);
    }

    public function test_GIVEN_the_wrong_password_WHEN_logging_in_THEN_it_returns_401_with_a_generic_message(): void
    {
        AdminUserModel::factory()->create(['email' => 'admin@example.com', 'password' => Hash::make('Senha123')]);

        $response = $this->postJson('/api/admin/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'errada']);

        $response->assertStatus(401)->assertJsonPath('message', 'Credenciais inválidas');
    }

    public function test_GIVEN_an_unknown_email_WHEN_logging_in_THEN_it_returns_401_with_the_same_generic_message(): void
    {
        $response = $this->postJson('/api/admin/v1/auth/login', ['email' => 'ninguem@example.com', 'password' => 'Senha123']);

        $response->assertStatus(401)->assertJsonPath('message', 'Credenciais inválidas');
    }

    public function test_GIVEN_an_authenticated_admin_WHEN_logging_out_THEN_the_token_is_revoked(): void
    {
        $admin = AdminUserModel::factory()->create();
        $token = $admin->createToken('admin')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/admin/v1/auth/logout');

        $response->assertStatus(200);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_GIVEN_a_fan_token_WHEN_calling_an_admin_guarded_route_THEN_it_is_rejected(): void
    {
        $fan = UserModel::factory()->create();
        $token = $fan->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/admin/v1/auth/logout');

        $response->assertStatus(401);
    }
}

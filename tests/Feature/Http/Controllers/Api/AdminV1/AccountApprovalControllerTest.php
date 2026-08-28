<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Http\Controllers\Api\AdminV1\AccountApprovalController;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use Tests\TestCase;

class AccountApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:admin', 'guard.admin', 'guard.super-admin'])->group(function () {
            Route::get('/api/__test/approvals/accounts', [AccountApprovalController::class, 'index']);
            Route::post('/api/__test/approvals/accounts/{accountType}/{id}/decide', [AccountApprovalController::class, 'decide']);
        });
    }

    /**
     * @return array<string, string>
     */
    private function authHeader(AdminUserModel $admin): array
    {
        return ['Authorization' => 'Bearer '.$admin->createToken('admin-panel')->plainTextToken];
    }

    private function superAdmin(): AdminUserModel
    {
        return AdminUserModel::factory()->superAdmin()->create();
    }

    public function test_GIVEN_pending_venues_and_promoters_WHEN_listing_approvals_THEN_it_returns_both_types_merged(): void
    {
        $admin = $this->superAdmin();
        VenueModel::factory()->create(['name' => 'Casa de Show']);
        PromoterModel::factory()->create(['name' => 'Produtora XYZ']);
        VenueModel::factory()->approved()->create();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/__test/approvals/accounts');

        $response->assertStatus(200);
        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');
        $types = array_column($data, 'type');

        $this->assertContains('venue', $types);
        $this->assertContains('promoter', $types);
        $this->assertCount(2, $data);
    }

    public function test_GIVEN_more_pending_accounts_than_the_page_size_WHEN_listing_approvals_THEN_it_paginates(): void
    {
        $admin = $this->superAdmin();
        /** @var int $pageSize */
        $pageSize = config('qor.pagination.admin_page_size');

        VenueModel::factory()->count($pageSize + 3)->create();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/__test/approvals/accounts?page=2');

        $response->assertStatus(200)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', $pageSize)
            ->assertJsonPath('total', $pageSize + 3);

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_GIVEN_a_pending_venue_WHEN_approving_it_THEN_it_becomes_approved(): void
    {
        $admin = $this->superAdmin();
        $venue = VenueModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/__test/approvals/accounts/venue/{$venue->id}/decide", ['outcome' => 'approved']);

        $response->assertStatus(200)
            ->assertJsonPath('data.outcome', 'approved')
            ->assertJsonPath('data.decidable_type', 'venue')
            ->assertJsonPath('data.decidable_id', $venue->id);

        $this->assertDatabaseHas('venues', ['id' => $venue->id, 'approval_status' => ApprovalStatus::Approved->value]);
    }

    public function test_GIVEN_a_reason_WHEN_rejecting_a_pending_promoter_THEN_it_becomes_rejected(): void
    {
        $admin = $this->superAdmin();
        $promoter = PromoterModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/__test/approvals/accounts/promoter/{$promoter->id}/decide", [
                'outcome' => 'rejected',
                'reason' => 'Dados de contato inválidos.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.outcome', 'rejected')
            ->assertJsonPath('data.reason', 'Dados de contato inválidos.');

        $this->assertDatabaseHas('promoters', ['id' => $promoter->id, 'approval_status' => ApprovalStatus::Rejected->value]);
    }

    public function test_GIVEN_no_reason_WHEN_rejecting_an_account_THEN_it_is_rejected_with_a_validation_error(): void
    {
        $admin = $this->superAdmin();
        $venue = VenueModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/__test/approvals/accounts/venue/{$venue->id}/decide", ['outcome' => 'rejected']);

        $response->assertStatus(422);
    }

    public function test_GIVEN_an_approved_venue_WHEN_suspending_it_THEN_it_becomes_suspended(): void
    {
        $admin = $this->superAdmin();
        $venue = VenueModel::factory()->approved()->create();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/__test/approvals/accounts/venue/{$venue->id}/decide", [
                'outcome' => 'suspended',
                'reason' => 'Reclamações recorrentes de usuários.',
            ]);

        $response->assertStatus(200)->assertJsonPath('data.outcome', 'suspended');

        $this->assertDatabaseHas('venues', ['id' => $venue->id, 'approval_status' => ApprovalStatus::Suspended->value]);
    }

    public function test_GIVEN_a_suspended_promoter_WHEN_lifting_the_suspension_THEN_it_becomes_approved_again(): void
    {
        $admin = $this->superAdmin();
        $promoter = PromoterModel::factory()->create(['approval_status' => ApprovalStatus::Suspended->value]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/__test/approvals/accounts/promoter/{$promoter->id}/decide", ['outcome' => 'suspension_lifted']);

        $response->assertStatus(200)->assertJsonPath('data.outcome', 'suspension_lifted');

        $this->assertDatabaseHas('promoters', ['id' => $promoter->id, 'approval_status' => ApprovalStatus::Approved->value]);
    }

    public function test_GIVEN_an_unrecognized_account_type_WHEN_deciding_THEN_it_returns_not_found(): void
    {
        $admin = $this->superAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/__test/approvals/accounts/gadget/1/decide', ['outcome' => 'approved']);

        $response->assertStatus(404)->assertJsonPath('message', 'Tipo de conta inválido.');
    }

    public function test_GIVEN_the_event_account_type_WHEN_deciding_THEN_the_domain_layer_rejects_it_as_unprocessable(): void
    {
        $admin = $this->superAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/__test/approvals/accounts/event/1/decide', ['outcome' => 'approved']);

        $response->assertStatus(422);
    }
}

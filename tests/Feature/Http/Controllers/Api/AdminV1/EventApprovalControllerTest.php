<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use QOR\App\Http\Controllers\Api\AdminV1\EventApprovalController;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use Tests\TestCase;

class EventApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route registered here only for this test suite: the orchestrator
        // wires routes/api_admin_v1.php centrally once every controller
        // task lands, to avoid merge conflicts between parallel agents.
        Route::middleware(['api', 'auth:admin', 'guard.admin', 'guard.super-admin'])
            ->prefix('api/admin/v1')
            ->group(function () {
                Route::get('approvals/events', [EventApprovalController::class, 'index']);
                Route::post('approvals/events/{id}/decide', [EventApprovalController::class, 'decide']);
            });
    }

    private function actingAsSuperAdmin(): AdminUserModel
    {
        $admin = AdminUserModel::factory()->superAdmin()->create();

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $admin;
    }

    public function test_GIVEN_a_mix_of_event_statuses_WHEN_listing_the_approval_queue_THEN_only_pending_review_events_are_returned(): void
    {
        $this->actingAsSuperAdmin();

        $pending = EventModel::factory()->pendingReview()->create(['title' => 'Show Pendente']);
        EventModel::factory()->published()->create(['title' => 'Show Publicado']);
        EventModel::factory()->create(['title' => 'Rascunho']);

        $response = $this->getJson('/api/admin/v1/approvals/events');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('total', 1);
    }

    public function test_GIVEN_more_pending_events_than_the_page_size_WHEN_listing_the_second_page_THEN_it_respects_admin_page_size(): void
    {
        $this->actingAsSuperAdmin();

        config(['qor.pagination.admin_page_size' => 2]);

        EventModel::factory()->pendingReview()->count(3)->create();

        $response = $this->getJson('/api/admin/v1/approvals/events?page=2');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3);
    }

    public function test_GIVEN_an_event_starting_in_the_future_WHEN_approved_THEN_it_becomes_published(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $event = EventModel::factory()->pendingReview()->create(['starts_at' => now()->addDays(5)]);

        $response = $this->postJson("/api/admin/v1/approvals/events/{$event->id}/decide", [
            'outcome' => 'approved',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.outcome', 'approved')
            ->assertJsonPath('data.decided_by', $admin->id);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'published']);
    }

    public function test_GIVEN_an_event_starting_in_the_past_WHEN_approved_THEN_it_is_closed_directly(): void
    {
        $this->actingAsSuperAdmin();
        $event = EventModel::factory()->pendingReview()->create(['starts_at' => now()->subDay()]);

        $response = $this->postJson("/api/admin/v1/approvals/events/{$event->id}/decide", [
            'outcome' => 'approved',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'ended']);
    }

    public function test_GIVEN_feedback_WHEN_rejecting_an_event_THEN_it_returns_to_draft_with_the_feedback_visible(): void
    {
        $this->actingAsSuperAdmin();
        $event = EventModel::factory()->pendingReview()->create();

        $response = $this->postJson("/api/admin/v1/approvals/events/{$event->id}/decide", [
            'outcome' => 'rejected',
            'feedback' => 'Faltam informações sobre o local.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.outcome', 'rejected')
            ->assertJsonPath('data.reason', 'Faltam informações sobre o local.');
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
    }

    public function test_GIVEN_no_feedback_WHEN_rejecting_an_event_THEN_it_returns_422(): void
    {
        $this->actingAsSuperAdmin();
        $event = EventModel::factory()->pendingReview()->create();

        $response = $this->postJson("/api/admin/v1/approvals/events/{$event->id}/decide", [
            'outcome' => 'rejected',
        ]);

        $response->assertStatus(422);
    }

    public function test_GIVEN_a_valid_request_WHEN_force_cancelling_an_event_THEN_it_becomes_cancelled(): void
    {
        $this->actingAsSuperAdmin();
        $event = EventModel::factory()->pendingReview()->create();

        $response = $this->postJson("/api/admin/v1/approvals/events/{$event->id}/decide", [
            'outcome' => 'force_cancelled',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.outcome', 'force_cancelled');
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled']);
    }

    public function test_GIVEN_an_outcome_not_allowed_for_events_WHEN_deciding_THEN_it_returns_a_validation_error(): void
    {
        $this->actingAsSuperAdmin();
        $event = EventModel::factory()->pendingReview()->create();

        $response = $this->postJson("/api/admin/v1/approvals/events/{$event->id}/decide", [
            'outcome' => 'suspended',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['outcome']);
    }

    public function test_GIVEN_an_unknown_event_id_WHEN_deciding_THEN_it_returns_422(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/admin/v1/approvals/events/999999/decide', [
            'outcome' => 'approved',
        ]);

        $response->assertStatus(422);
    }
}

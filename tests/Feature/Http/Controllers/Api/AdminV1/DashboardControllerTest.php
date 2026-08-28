<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use QOR\App\Http\Controllers\Api\AdminV1\DashboardController;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route registered here only for this test suite: the orchestrator
        // wires routes/api_admin_v1.php centrally once every controller
        // task lands, to avoid merge conflicts between parallel agents.
        Route::middleware(['api', 'auth:admin', 'guard.admin'])
            ->prefix('api/admin/v1')
            ->group(function () {
                Route::get('dashboard', [DashboardController::class, 'index']);
            });
    }

    private function actingAsVenueAdmin(): VenueModel
    {
        $admin = AdminUserModel::factory()->create();
        $venue = VenueModel::factory()->approved()->create(['venue_admin_user_id' => $admin->id]);

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $venue;
    }

    private function actingAsPromoter(): PromoterModel
    {
        $admin = AdminUserModel::factory()->create();
        $promoter = PromoterModel::factory()->approved()->create(['user_id' => $admin->id]);

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $promoter;
    }

    private function genreId(): int
    {
        return DB::table('genres')->insertGetId([
            'name' => 'Rock',
            'slug' => 'rock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_GIVEN_a_venue_admin_with_events_across_multiple_statuses_WHEN_fetching_the_dashboard_THEN_all_of_their_events_are_returned(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();

        EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'draft',
            'title' => 'Evento em Rascunho',
        ]);
        EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'published',
            'title' => 'Evento Publicado',
        ]);
        EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'cancelled',
            'title' => 'Evento Cancelado',
        ]);

        $response = $this->getJson('/api/admin/v1/dashboard');

        $response->assertStatus(200)->assertJsonCount(3, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->all();
        $this->assertContains('draft', $statuses);
        $this->assertContains('published', $statuses);
        $this->assertContains('cancelled', $statuses);
    }

    public function test_GIVEN_a_promoter_organizer_WHEN_fetching_the_dashboard_THEN_only_their_own_events_are_returned(): void
    {
        $promoter = $this->actingAsPromoter();
        $genreId = $this->genreId();

        EventModel::factory()->create([
            'created_by_type' => 'promoter',
            'created_by_id' => $promoter->id,
            'genre_id' => $genreId,
            'title' => 'Meu Evento',
        ]);
        EventModel::factory()->create([
            'created_by_type' => 'promoter',
            'created_by_id' => $promoter->id + 999,
            'genre_id' => $genreId,
            'title' => 'Evento de Outro Promoter',
        ]);

        $response = $this->getJson('/api/admin/v1/dashboard');

        $response->assertStatus(200)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Meu Evento');
    }

    public function test_GIVEN_events_owned_by_the_organizer_WHEN_fetching_the_dashboard_THEN_the_count_fields_are_present_and_null(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();

        EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
        ]);

        $response = $this->getJson('/api/admin/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.view_count', null)
            ->assertJsonPath('data.0.favorite_count', null)
            ->assertJsonPath('data.0.ticket_click_count', null)
            ->assertJsonPath('data.0.interested_count', null);
    }

    public function test_GIVEN_no_authenticated_admin_WHEN_fetching_the_dashboard_THEN_it_returns_401(): void
    {
        $response = $this->getJson('/api/admin/v1/dashboard');

        $response->assertStatus(401);
    }

    public function test_GIVEN_an_admin_with_no_venue_or_promoter_account_WHEN_fetching_the_dashboard_THEN_it_returns_403(): void
    {
        $admin = AdminUserModel::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');

        $response = $this->getJson('/api/admin/v1/dashboard');

        $response->assertStatus(403);
    }
}

<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Http\Controllers\Api\AdminV1\EventController;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\SubscriptionModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use Tests\TestCase;

class EventControllerTest extends TestCase
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
                Route::get('events', [EventController::class, 'index']);
                Route::post('events', [EventController::class, 'store']);
                Route::post('events/{id}/submit', [EventController::class, 'submit']);
                Route::patch('events/{id}', [EventController::class, 'update']);
                Route::post('events/{id}/duplicate', [EventController::class, 'duplicate']);
                Route::post('events/{id}/cancel', [EventController::class, 'cancel']);
                Route::delete('events/{id}', [EventController::class, 'destroy']);
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Show de Rock',
            'description' => 'Uma noite de rock.',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'city' => 'vitoria',
            'genre_id' => $this->genreId(),
            'is_free' => true,
        ], $overrides);
    }

    public function test_GIVEN_an_approved_venue_organizer_WHEN_creating_an_event_THEN_it_defaults_the_address_from_the_venue(): void
    {
        $venue = $this->actingAsVenueAdmin();

        $response = $this->postJson('/api/admin/v1/events', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Show de Rock')
            ->assertJsonPath('data.address', $venue->address)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by_type', 'venue_admin')
            ->assertJsonPath('data.created_by_id', $venue->id);
        $this->assertDatabaseHas('events', ['title' => 'Show de Rock', 'created_by_id' => $venue->id]);
    }

    public function test_GIVEN_an_approved_promoter_organizer_WHEN_creating_an_event_with_an_explicit_address_THEN_it_persists_that_address(): void
    {
        $promoter = $this->actingAsPromoter();

        $response = $this->postJson('/api/admin/v1/events', $this->validPayload([
            'address' => 'Praça do Papa, s/n',
            'is_free' => false,
            'ticket_url' => 'https://ingressos.example.com/show',
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.address', 'Praça do Papa, s/n')
            ->assertJsonPath('data.created_by_type', 'promoter')
            ->assertJsonPath('data.created_by_id', $promoter->id);
    }

    public function test_GIVEN_an_unapproved_organizer_WHEN_creating_an_event_THEN_it_returns_422(): void
    {
        $admin = AdminUserModel::factory()->create();
        VenueModel::factory()->create(['venue_admin_user_id' => $admin->id]);
        Sanctum::actingAs($admin, ['*'], 'admin');

        $response = $this->postJson('/api/admin/v1/events', $this->validPayload());

        $response->assertStatus(422);
    }

    public function test_GIVEN_a_cover_image_WHEN_creating_an_event_THEN_it_persists_the_stored_url(): void
    {
        Storage::fake('s3');
        $this->actingAsVenueAdmin();
        $file = UploadedFile::fake()->image('capa.jpg', 800, 600);

        $response = $this->post('/api/admin/v1/events', array_merge($this->validPayload(), [
            'cover_image' => $file,
        ]));

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.cover_image_url'));
    }

    public function test_GIVEN_events_belonging_to_multiple_organizers_WHEN_listing_THEN_only_the_authenticated_organizers_events_are_returned(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();

        EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $venue->id, 'genre_id' => $genreId, 'title' => 'Meu Evento']);
        EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $venue->id + 999, 'genre_id' => $genreId, 'title' => 'Evento de Outra Venue']);

        $response = $this->getJson('/api/admin/v1/events');

        $response->assertStatus(200)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Meu Evento');
    }

    public function test_GIVEN_multiple_events_with_distinct_tagged_promoters_WHEN_listing_THEN_each_event_shows_only_its_own_promoters(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();

        $eventA = EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $venue->id, 'genre_id' => $genreId]);
        $eventB = EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $venue->id, 'genre_id' => $genreId]);
        $promoterA = PromoterModel::factory()->approved()->create(['name' => 'Produtora A']);
        $promoterB = PromoterModel::factory()->approved()->create(['name' => 'Produtora B']);

        DB::table('event_promoter')->insert([
            ['event_id' => $eventA->id, 'promoter_id' => $promoterA->id, 'tagged_at' => now()],
            ['event_id' => $eventB->id, 'promoter_id' => $promoterB->id, 'tagged_at' => now()],
        ]);

        $response = $this->getJson('/api/admin/v1/events');

        $response->assertStatus(200);
        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame('Produtora A', $byId[$eventA->id]['promoters'][0]['name']);
        $this->assertSame('Produtora B', $byId[$eventB->id]['promoters'][0]['name']);
    }

    public function test_GIVEN_a_draft_event_owned_by_the_organizer_WHEN_submitting_for_review_THEN_it_transitions_to_pending_review(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $venue->id, 'genre_id' => $genreId]);
        SubscriptionModel::factory()->create([
            'subscribable_type' => SubscribableType::Venue->value,
            'subscribable_id' => $venue->id,
        ]);

        $response = $this->postJson("/api/admin/v1/events/{$event->id}/submit");

        $response->assertStatus(200)->assertJsonPath('data.status', 'pending_review');
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'pending_review']);
    }

    public function test_GIVEN_an_event_owned_by_a_different_organizer_WHEN_submitting_for_review_THEN_it_returns_403(): void
    {
        $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $otherVenue = VenueModel::factory()->approved()->create();
        $event = EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $otherVenue->id, 'genre_id' => $genreId]);

        $response = $this->postJson("/api/admin/v1/events/{$event->id}/submit");

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
    }

    public function test_GIVEN_an_event_owned_by_the_organizer_WHEN_deleting_THEN_it_is_removed(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $venue->id, 'genre_id' => $genreId]);

        $response = $this->deleteJson("/api/admin/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_GIVEN_an_event_owned_by_a_different_organizer_WHEN_deleting_THEN_it_returns_403_and_the_event_survives(): void
    {
        $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $otherVenue = VenueModel::factory()->approved()->create();
        $event = EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $otherVenue->id, 'genre_id' => $genreId]);

        $response = $this->deleteJson("/api/admin/v1/events/{$event->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_GIVEN_a_draft_event_owned_by_the_organizer_WHEN_editing_THEN_changes_are_persisted(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'draft',
            'title' => 'Título Original',
        ]);

        $response = $this->patchJson("/api/admin/v1/events/{$event->id}", ['title' => 'Título Editado']);

        $response->assertStatus(200)->assertJsonPath('data.title', 'Título Editado');
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Título Editado']);
    }

    public function test_GIVEN_a_published_event_owned_by_the_organizer_WHEN_editing_only_the_description_THEN_it_returns_200(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'published',
        ]);

        $response = $this->patchJson("/api/admin/v1/events/{$event->id}", ['description' => 'Nova descrição.']);

        $response->assertStatus(200)->assertJsonPath('data.description', 'Nova descrição.');
        $this->assertDatabaseHas('events', ['id' => $event->id, 'description' => 'Nova descrição.', 'status' => 'published']);
    }

    public function test_GIVEN_a_published_event_owned_by_the_organizer_WHEN_editing_the_title_THEN_it_returns_422(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'published',
            'title' => 'Título Original',
        ]);

        $response = $this->patchJson("/api/admin/v1/events/{$event->id}", ['title' => 'Título Novo']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Título Original']);
    }

    public function test_GIVEN_an_event_owned_by_a_different_organizer_WHEN_editing_THEN_it_returns_403(): void
    {
        $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $otherVenue = VenueModel::factory()->approved()->create();
        $event = EventModel::factory()->create(['created_by_type' => 'venue_admin', 'created_by_id' => $otherVenue->id, 'genre_id' => $genreId, 'status' => 'draft']);

        $response = $this->patchJson("/api/admin/v1/events/{$event->id}", ['title' => 'Tentativa de Edição']);

        $response->assertStatus(403);
    }

    public function test_GIVEN_an_event_owned_by_the_organizer_WHEN_duplicating_THEN_a_new_draft_is_created_and_the_original_is_untouched(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'published',
            'title' => 'Evento Original',
        ]);
        $newStartsAt = now()->addMonth()->toIso8601String();

        $response = $this->postJson("/api/admin/v1/events/{$event->id}/duplicate", ['starts_at' => $newStartsAt]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Evento Original')
            ->assertJsonPath('data.status', 'draft');
        $this->assertNotEquals($event->id, $response->json('data.id'));
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'published']);
        $this->assertDatabaseHas('events', ['id' => $response->json('data.id'), 'status' => 'draft', 'title' => 'Evento Original']);
    }

    public function test_GIVEN_a_published_event_owned_by_the_organizer_WHEN_cancelling_THEN_it_transitions_to_cancelled(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'published',
        ]);

        $response = $this->postJson("/api/admin/v1/events/{$event->id}/cancel");

        $response->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled']);
    }

    public function test_GIVEN_a_draft_event_owned_by_the_organizer_WHEN_cancelling_THEN_it_returns_422(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/admin/v1/events/{$event->id}/cancel");

        $response->assertStatus(422);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
    }

    public function test_GIVEN_an_approved_venue_organizer_and_valid_promoter_ids_WHEN_creating_an_event_THEN_the_response_includes_tagged_promoters(): void
    {
        $this->actingAsVenueAdmin();
        $promoter = PromoterModel::factory()->approved()->create([
            'name' => 'Produtora Legal',
            'contact_phone' => '27988887777',
            'contact_email' => 'contato@produtoralegal.com',
            'instagram' => '@produtoralegal',
            'tiktok' => '@produtoralegal',
        ]);

        $response = $this->postJson('/api/admin/v1/events', $this->validPayload([
            'promoter_ids' => [$promoter->id],
        ]));

        $response->assertStatus(201)
            ->assertJsonCount(1, 'data.promoters')
            ->assertJsonPath('data.promoters.0.id', $promoter->id)
            ->assertJsonPath('data.promoters.0.name', 'Produtora Legal')
            ->assertJsonPath('data.promoters.0.contact_phone', '27988887777')
            ->assertJsonPath('data.promoters.0.contact_email', 'contato@produtoralegal.com')
            ->assertJsonPath('data.promoters.0.instagram', '@produtoralegal')
            ->assertJsonPath('data.promoters.0.tiktok', '@produtoralegal');

        $this->assertDatabaseHas('event_promoter', [
            'event_id' => $response->json('data.id'),
            'promoter_id' => $promoter->id,
        ]);
    }

    public function test_GIVEN_a_draft_event_owned_by_the_organizer_WHEN_editing_to_add_promoter_tags_THEN_the_response_includes_them(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'draft',
        ]);
        $promoter = PromoterModel::factory()->approved()->create();

        $response = $this->patchJson("/api/admin/v1/events/{$event->id}", [
            'promoter_ids' => [$promoter->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.promoters')
            ->assertJsonPath('data.promoters.0.id', $promoter->id);
        $this->assertDatabaseHas('event_promoter', [
            'event_id' => $event->id,
            'promoter_id' => $promoter->id,
        ]);
    }

    public function test_GIVEN_a_promoter_tagged_but_not_the_creator_WHEN_attempting_to_edit_the_event_THEN_it_returns_403(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $genreId = $this->genreId();
        $event = EventModel::factory()->create([
            'created_by_type' => 'venue_admin',
            'created_by_id' => $venue->id,
            'genre_id' => $genreId,
            'status' => 'draft',
        ]);
        $taggedPromoter = PromoterModel::factory()->approved()->create();
        DB::table('event_promoter')->insert([
            'event_id' => $event->id,
            'promoter_id' => $taggedPromoter->id,
            'tagged_at' => now(),
        ]);

        // Log the tagged promoter's own admin user in — tagging is contact-display
        // only and grants no edit rights (ADMIN-24).
        $taggedPromoterAdmin = AdminUserModel::find($taggedPromoter->user_id);
        Sanctum::actingAs($taggedPromoterAdmin, ['*'], 'admin');

        $response = $this->patchJson("/api/admin/v1/events/{$event->id}", ['title' => 'Tentativa de Edição']);

        $response->assertStatus(403);
    }
}

<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_published_upcoming_events_WHEN_listing_THEN_it_returns_200_with_the_envelope(): void
    {
        EventModel::factory()->published()->create(['starts_at' => now()->addDays(3)]);
        EventModel::factory()->create(['status' => EventStatus::Draft->value]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'title', 'starts_at', 'status']], 'next_cursor'])
            ->assertJsonCount(1, 'data');
    }

    public function test_GIVEN_an_invalid_city_WHEN_listing_THEN_it_returns_422(): void
    {
        $response = $this->getJson('/api/v1/events?city=not-a-city');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['city']]);
    }

    public function test_GIVEN_a_city_filter_with_no_matches_WHEN_listing_THEN_it_returns_200_with_an_empty_list(): void
    {
        EventModel::factory()->published()->create(['city' => City::Vitoria->value, 'starts_at' => now()->addDay()]);

        $response = $this->getJson('/api/v1/events?city='.City::Cariacica->value);

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_GIVEN_an_existing_published_event_WHEN_showing_THEN_it_returns_200_with_full_detail(): void
    {
        $event = EventModel::factory()->published()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'title', 'description', 'tagged_promoters']])
            ->assertJsonPath('data.id', $event->id);
    }

    public function test_GIVEN_a_cancelled_event_reached_via_stale_link_WHEN_showing_THEN_it_returns_200_not_404(): void
    {
        $event = EventModel::factory()->create(['status' => EventStatus::Cancelled->value]);

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)->assertJsonPath('data.status', EventStatus::Cancelled->value);
    }

    public function test_GIVEN_an_event_with_a_tagged_promoter_WHEN_showing_THEN_the_promoter_is_included(): void
    {
        $event = EventModel::factory()->published()->create();
        $promoter = PromoterModel::factory()->create();
        $event->promoters()->attach($promoter->id, ['tagged_at' => now()]);

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.tagged_promoters')
            ->assertJsonPath('data.tagged_promoters.0.id', $promoter->id);
    }

    public function test_GIVEN_a_nonexistent_event_id_WHEN_showing_THEN_it_returns_a_pt_br_404_envelope(): void
    {
        $response = $this->getJson('/api/v1/events/999999');

        $response->assertStatus(404)->assertExactJson(['message' => 'Evento não encontrado.']);
    }

    public function test_GIVEN_a_non_numeric_event_id_WHEN_showing_THEN_it_returns_a_pt_br_404_envelope(): void
    {
        $response = $this->getJson('/api/v1/events/not-a-number');

        $response->assertStatus(404)->assertExactJson(['message' => 'Recurso não encontrado.']);
    }
}

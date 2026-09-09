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

    public function test_GIVEN_a_geocoded_published_event_inside_a_box_WHEN_querying_the_map_THEN_it_returns_with_coordinates(): void
    {
        EventModel::factory()->published()->create([
            'latitude' => -20.3155,
            'longitude' => -40.3128,
        ]);

        $response = $this->getJson('/api/v1/events/map?north=-20.0&south=-21.0&east=-40.0&west=-41.0');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latitude', -20.3155)
            ->assertJsonPath('data.0.longitude', -40.3128);
    }

    public function test_GIVEN_a_city_query_WHEN_querying_the_map_THEN_only_that_citys_geocoded_events_return(): void
    {
        EventModel::factory()->published()->create([
            'city' => City::Vitoria->value,
            'latitude' => -20.3155,
            'longitude' => -40.3128,
        ]);
        EventModel::factory()->published()->create([
            'city' => City::Serra->value,
            'latitude' => -20.1289,
            'longitude' => -40.3078,
        ]);

        $response = $this->getJson('/api/v1/events/map?city='.City::Vitoria->value);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_GIVEN_neither_a_box_nor_a_city_WHEN_querying_the_map_THEN_it_returns_422(): void
    {
        $response = $this->getJson('/api/v1/events/map');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_GIVEN_an_area_with_no_geocoded_events_WHEN_querying_the_map_THEN_it_returns_an_empty_array_not_an_error(): void
    {
        EventModel::factory()->published()->create(['latitude' => null, 'longitude' => null]);

        $response = $this->getJson('/api/v1/events/map?north=-20.0&south=-21.0&east=-40.0&west=-41.0');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_GIVEN_the_map_route_is_called_WHEN_no_auth_token_is_present_THEN_it_still_succeeds(): void
    {
        $response = $this->getJson('/api/v1/events/map?city='.City::Vitoria->value);

        $response->assertStatus(200);
    }
}

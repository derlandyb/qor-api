<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\MapBounds;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\EloquentEventRepository;
use Tests\TestCase;

class EloquentEventRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_more_published_events_than_the_page_size_WHEN_finding_upcoming_THEN_cursor_pagination_returns_correct_page_boundaries(): void
    {
        config(['qor.pagination.public_page_size' => 2]);

        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        foreach (range(1, 5) as $i) {
            EventModel::factory()->published()->create([
                'starts_at' => now()->addDays($i),
                'genre_id' => $genreId,
                'city' => City::Vitoria->value,
            ]);
        }

        $repository = new EloquentEventRepository();

        $firstPage = $repository->findUpcoming(null, null, null);
        $this->assertCount(2, $firstPage->items);
        $this->assertNotNull($firstPage->nextCursor);

        $secondPage = $repository->findUpcoming(null, null, $firstPage->nextCursor);
        $this->assertCount(2, $secondPage->items);
        $this->assertNotNull($secondPage->nextCursor);

        $thirdPage = $repository->findUpcoming(null, null, $secondPage->nextCursor);
        $this->assertCount(1, $thirdPage->items);
        $this->assertNull($thirdPage->nextCursor);

        $seenIds = array_merge(
            array_map(fn ($e) => $e->id, $firstPage->items),
            array_map(fn ($e) => $e->id, $secondPage->items),
            array_map(fn ($e) => $e->id, $thirdPage->items),
        );
        $this->assertCount(5, array_unique($seenIds));
    }

    public function test_GIVEN_events_in_multiple_cities_WHEN_filtering_by_city_THEN_only_matching_events_are_returned(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        EventModel::factory()->published()->create(['city' => City::Vitoria->value, 'genre_id' => $genreId]);
        EventModel::factory()->published()->create(['city' => City::Serra->value, 'genre_id' => $genreId]);

        $repository = new EloquentEventRepository();

        $page = $repository->findUpcoming(City::Serra, null, null);

        $this->assertCount(1, $page->items);
        $this->assertSame(City::Serra, $page->items[0]->city);
    }

    public function test_GIVEN_a_non_existent_event_id_WHEN_finding_by_id_THEN_it_returns_null(): void
    {
        $repository = new EloquentEventRepository();

        $this->assertNull($repository->findById(999999));
    }

    public function test_GIVEN_events_with_different_genres_WHEN_filtering_by_genre_THEN_only_matching_events_are_returned(): void
    {
        $rockId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);
        $sambaId = DB::table('genres')->insertGetId(['name' => 'Samba', 'slug' => 'samba', 'created_at' => now(), 'updated_at' => now()]);

        EventModel::factory()->published()->create(['genre_id' => $rockId]);
        EventModel::factory()->published()->create(['genre_id' => $sambaId]);

        $repository = new EloquentEventRepository();

        $page = $repository->findUpcoming(null, $sambaId, null);

        $this->assertCount(1, $page->items);
        $this->assertSame($sambaId, $page->items[0]->genreId);
        $this->assertSame('Samba', $page->items[0]->genreName);
    }

    public function test_GIVEN_a_persisted_event_WHEN_finding_by_id_THEN_the_genre_name_is_resolved_via_the_genre_relation(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Reggae', 'slug' => 'reggae', 'created_at' => now(), 'updated_at' => now()]);
        $model = EventModel::factory()->create(['genre_id' => $genreId]);

        $repository = new EloquentEventRepository();

        $event = $repository->findById($model->id);

        $this->assertNotNull($event);
        $this->assertSame($genreId, $event->genreId);
        $this->assertSame('Reggae', $event->genreName);
    }

    public function test_GIVEN_multiple_events_sharing_a_genre_WHEN_finding_by_ids_THEN_the_genre_is_resolved_in_a_constant_number_of_queries(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Funk', 'slug' => 'funk', 'created_at' => now(), 'updated_at' => now()]);
        $models = EventModel::factory()->count(5)->create(['genre_id' => $genreId]);

        $repository = new EloquentEventRepository();

        DB::enableQueryLog();
        $events = $repository->findByIds($models->pluck('id')->all());
        $queryCountForFive = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->assertCount(5, $events);
        foreach ($events as $event) {
            $this->assertSame('Funk', $event->genreName);
        }

        $moreModels = EventModel::factory()->count(10)->create(['genre_id' => $genreId]);
        DB::flushQueryLog();

        $repository->findByIds(array_merge($models->pluck('id')->all(), $moreModels->pluck('id')->all()));
        $queryCountForFifteen = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->assertSame(
            $queryCountForFive,
            $queryCountForFifteen,
            'Genre resolution must be eager-loaded (constant query count), not N+1.'
        );
    }

    public function test_GIVEN_a_new_domain_event_WHEN_saving_THEN_it_is_persisted_and_assigned_an_id(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        $event = new Event(
            id: null,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 1,
            title: 'Show de Rock',
            description: 'Uma noite de rock.',
            startsAt: new \DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: $genreId,
            genreName: 'Rock',
            address: 'Rua das Flores, 123',
            isFree: true,
        );

        $repository = new EloquentEventRepository();

        $saved = $repository->save($event);

        $this->assertNotNull($saved->id);
        $this->assertSame('Show de Rock', $saved->title);
        $this->assertDatabaseHas('events', ['id' => $saved->id, 'title' => 'Show de Rock']);
    }

    public function test_GIVEN_a_persisted_event_WHEN_deleting_THEN_it_is_no_longer_findable(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);
        $model = EventModel::factory()->create(['genre_id' => $genreId]);
        $repository = new EloquentEventRepository();

        $repository->delete($model->id);

        $this->assertNull($repository->findById($model->id));
    }

    public function test_GIVEN_events_from_different_creators_WHEN_finding_by_creator_THEN_only_that_creators_events_are_returned(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        EventModel::factory()->create([
            'genre_id' => $genreId,
            'created_by_type' => EventCreatedByType::VenueAdmin->value,
            'created_by_id' => 10,
        ]);
        EventModel::factory()->create([
            'genre_id' => $genreId,
            'created_by_type' => EventCreatedByType::VenueAdmin->value,
            'created_by_id' => 20,
        ]);
        EventModel::factory()->create([
            'genre_id' => $genreId,
            'created_by_type' => EventCreatedByType::Promoter->value,
            'created_by_id' => 10,
        ]);

        $repository = new EloquentEventRepository();

        $events = $repository->findByCreator(EventCreatedByType::VenueAdmin, 10);

        $this->assertCount(1, $events);
        $this->assertSame(EventCreatedByType::VenueAdmin, $events[0]->createdByType);
        $this->assertSame(10, $events[0]->createdById);
    }

    public function test_GIVEN_a_creator_with_multiple_events_WHEN_finding_by_creator_THEN_they_are_ordered_deterministically_by_starts_at_descending(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        $earlier = EventModel::factory()->create([
            'genre_id' => $genreId,
            'created_by_type' => EventCreatedByType::VenueAdmin->value,
            'created_by_id' => 30,
            'starts_at' => now()->addDays(1),
        ]);
        $later = EventModel::factory()->create([
            'genre_id' => $genreId,
            'created_by_type' => EventCreatedByType::VenueAdmin->value,
            'created_by_id' => 30,
            'starts_at' => now()->addDays(5),
        ]);

        $repository = new EloquentEventRepository();

        $events = $repository->findByCreator(EventCreatedByType::VenueAdmin, 30);

        $this->assertCount(2, $events);
        $this->assertSame($later->id, $events[0]->id);
        $this->assertSame($earlier->id, $events[1]->id);
    }

    public function test_GIVEN_published_events_past_and_future_and_a_cancelled_past_event_WHEN_finding_published_past_end_THEN_only_the_published_past_event_is_returned(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        $pastPublished = EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'starts_at' => now()->subHour(),
        ]);
        EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'starts_at' => now()->addHour(),
        ]);
        EventModel::factory()->create([
            'genre_id' => $genreId,
            'status' => EventStatus::Cancelled->value,
            'starts_at' => now()->subHour(),
        ]);

        $repository = new EloquentEventRepository();

        $events = $repository->findPublishedPastEnd();

        $this->assertCount(1, $events);
        $this->assertSame($pastPublished->id, $events[0]->id);
    }

    public function test_GIVEN_events_inside_and_outside_a_bounding_box_WHEN_finding_map_events_THEN_only_the_inside_ones_return(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        $inside = EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'latitude' => -20.3155,
            'longitude' => -40.3128,
        ]);
        EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'latitude' => -3.7172,
            'longitude' => -38.5433,
        ]);

        $repository = new EloquentEventRepository();

        $bounds = new MapBounds(north: -20.0, south: -21.0, east: -40.0, west: -41.0);
        $events = $repository->findMapEvents($bounds, null);

        $this->assertCount(1, $events);
        $this->assertSame($inside->id, $events[0]->id);
    }

    public function test_GIVEN_a_city_mode_query_WHEN_finding_map_events_THEN_only_that_citys_geocoded_events_return(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        $inVitoria = EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'city' => City::Vitoria->value,
            'latitude' => -20.3155,
            'longitude' => -40.3128,
        ]);
        EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'city' => City::Serra->value,
            'latitude' => -20.1289,
            'longitude' => -40.3078,
        ]);

        $repository = new EloquentEventRepository();

        $events = $repository->findMapEvents(null, City::Vitoria);

        $this->assertCount(1, $events);
        $this->assertSame($inVitoria->id, $events[0]->id);
    }

    public function test_GIVEN_an_event_with_null_coordinates_inside_the_box_WHEN_finding_map_events_THEN_it_is_excluded(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'latitude' => null,
            'longitude' => null,
        ]);

        $repository = new EloquentEventRepository();

        $bounds = new MapBounds(north: 90.0, south: -90.0, east: 180.0, west: -180.0);
        $events = $repository->findMapEvents($bounds, null);

        $this->assertCount(0, $events);
    }

    public function test_GIVEN_a_draft_event_with_coordinates_inside_the_box_WHEN_finding_map_events_THEN_it_is_excluded(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        EventModel::factory()->create([
            'genre_id' => $genreId,
            'status' => EventStatus::Draft->value,
            'latitude' => -20.3155,
            'longitude' => -40.3128,
        ]);

        $repository = new EloquentEventRepository();

        $bounds = new MapBounds(north: -20.0, south: -21.0, east: -40.0, west: -41.0);
        $events = $repository->findMapEvents($bounds, null);

        $this->assertCount(0, $events);
    }
}

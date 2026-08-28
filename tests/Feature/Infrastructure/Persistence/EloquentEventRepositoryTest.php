<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
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
}

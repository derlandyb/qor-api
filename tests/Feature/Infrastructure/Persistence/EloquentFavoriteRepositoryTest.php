<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Social\Enum\FavoriteState;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\FavoriteModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentEventRepository;
use QOR\App\Infrastructure\Persistence\EloquentFavoriteRepository;
use Tests\TestCase;

class EloquentFavoriteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_no_existing_favorite_WHEN_toggling_THEN_it_is_created_and_state_is_favorited(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $repository = new EloquentFavoriteRepository(new EloquentEventRepository());

        $state = $repository->toggle($user->id, $event->id);

        $this->assertSame(FavoriteState::Favorited, $state);
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'event_id' => $event->id]);
    }

    public function test_GIVEN_an_existing_favorite_WHEN_toggling_again_THEN_it_is_removed_and_state_is_unfavorited(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();
        FavoriteModel::create(['user_id' => $user->id, 'event_id' => $event->id, 'created_at' => now()]);

        $repository = new EloquentFavoriteRepository(new EloquentEventRepository());

        $state = $repository->toggle($user->id, $event->id);

        $this->assertSame(FavoriteState::Unfavorited, $state);
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'event_id' => $event->id]);
    }

    public function test_GIVEN_repeated_toggles_WHEN_toggling_twice_THEN_the_result_is_idempotent(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $repository = new EloquentFavoriteRepository(new EloquentEventRepository());

        $repository->toggle($user->id, $event->id);
        $repository->toggle($user->id, $event->id);

        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'event_id' => $event->id]);
    }

    public function test_GIVEN_favorites_on_published_and_non_published_events_WHEN_listing_for_user_THEN_only_the_published_events_favorite_is_returned(): void
    {
        $user = UserModel::factory()->create();
        $published = EventModel::factory()->published()->create();
        $draft = EventModel::factory()->create(['status' => EventStatus::Draft->value]);

        FavoriteModel::create(['user_id' => $user->id, 'event_id' => $published->id, 'created_at' => now()]);
        FavoriteModel::create(['user_id' => $user->id, 'event_id' => $draft->id, 'created_at' => now()]);

        $repository = new EloquentFavoriteRepository(new EloquentEventRepository());

        $page = $repository->listForUser($user->id, null);

        $this->assertCount(1, $page->items);
        $this->assertSame($published->id, $page->items[0]->id);
    }

    public function test_GIVEN_more_favorites_than_the_page_size_WHEN_listing_for_user_THEN_cursor_pagination_returns_correct_page_boundaries(): void
    {
        config(['qor.pagination.public_page_size' => 2]);

        $user = UserModel::factory()->create();

        foreach (range(1, 3) as $i) {
            $event = EventModel::factory()->published()->create();
            FavoriteModel::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'created_at' => now()->addSeconds($i),
            ]);
        }

        $repository = new EloquentFavoriteRepository(new EloquentEventRepository());

        $firstPage = $repository->listForUser($user->id, null);
        $this->assertCount(2, $firstPage->items);
        $this->assertNotNull($firstPage->nextCursor);

        $secondPage = $repository->listForUser($user->id, $firstPage->nextCursor);
        $this->assertCount(1, $secondPage->items);
        $this->assertNull($secondPage->nextCursor);
    }
}

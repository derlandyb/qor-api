<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use Tests\TestCase;

class CloseEndedEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_published_past_future_and_cancelled_events_WHEN_running_the_command_THEN_only_the_published_past_event_is_ended(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        $pastPublished = EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'starts_at' => now()->subHour(),
        ]);
        $futurePublished = EventModel::factory()->published()->create([
            'genre_id' => $genreId,
            'starts_at' => now()->addHour(),
        ]);
        $pastCancelled = EventModel::factory()->create([
            'genre_id' => $genreId,
            'status' => EventStatus::Cancelled->value,
            'starts_at' => now()->subHour(),
        ]);

        $this->artisan('events:close-ended')->assertExitCode(0);

        $this->assertDatabaseHas('events', ['id' => $pastPublished->id, 'status' => EventStatus::Ended->value]);
        $this->assertDatabaseHas('events', ['id' => $futurePublished->id, 'status' => EventStatus::Published->value]);
        $this->assertDatabaseHas('events', ['id' => $pastCancelled->id, 'status' => EventStatus::Cancelled->value]);
    }
}

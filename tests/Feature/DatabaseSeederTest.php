<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Shared\Enum\City;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_fresh_database_WHEN_seeding_THEN_it_produces_fans_venues_promoters_and_events_across_all_cities_statuses_and_genres(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, DB::table('users')->count());
        $this->assertGreaterThan(0, DB::table('venues')->count());
        $this->assertGreaterThan(0, DB::table('promoters')->count());

        foreach (City::cases() as $city) {
            $this->assertDatabaseHas('events', ['city' => $city->value]);
        }

        foreach (EventStatus::cases() as $status) {
            $this->assertDatabaseHas('events', ['status' => $status->value]);
        }

        $seededGenreCount = DB::table('genres')->count();
        $genresUsedByEvents = DB::table('events')->distinct()->count('genre_id');
        $this->assertSame($seededGenreCount, $genresUsedByEvents);
    }
}

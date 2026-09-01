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

        $this->assertDatabaseHas('admin_users', [
            'email' => 'superadmin@qor.dev',
            'is_super_admin' => true,
        ]);
    }

    public function test_GIVEN_the_admin_user_seeder_already_ran_WHEN_seeding_again_THEN_it_does_not_duplicate_the_super_admin_row(): void
    {
        $this->seed(\Database\Seeders\AdminUserSeeder::class);
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $this->assertSame(
            1,
            DB::table('admin_users')->where('email', 'superadmin@qor.dev')->count(),
        );
    }
}

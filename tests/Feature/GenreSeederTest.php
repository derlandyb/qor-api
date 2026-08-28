<?php

namespace Tests\Feature;

use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenreSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_the_genres_table_WHEN_the_genre_seeder_runs_THEN_it_seeds_the_initial_genre_set(): void
    {
        $this->seed(GenreSeeder::class);

        $this->assertGreaterThanOrEqual(10, DB::table('genres')->count());
        $this->assertTrue(DB::table('genres')->where('slug', 'rock')->exists());
    }
}

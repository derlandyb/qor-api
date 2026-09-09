<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Infrastructure\Persistence\EloquentGenreRepository;
use Tests\TestCase;

class EloquentGenreRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_an_existing_genre_id_WHEN_finding_the_name_THEN_it_is_returned(): void
    {
        $genreId = DB::table('genres')->insertGetId([
            'name' => 'Rock',
            'slug' => 'rock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repository = new EloquentGenreRepository();

        $this->assertSame('Rock', $repository->findNameById($genreId));
    }

    public function test_GIVEN_a_non_existent_genre_id_WHEN_finding_the_name_THEN_it_throws(): void
    {
        $repository = new EloquentGenreRepository();

        $this->expectException(ModelNotFoundException::class);

        $repository->findNameById(999999);
    }
}

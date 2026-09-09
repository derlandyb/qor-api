<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Event\Genre;
use QOR\App\Infrastructure\Persistence\Eloquent\GenreModel;
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

    public function test_GIVEN_a_new_genre_WHEN_saving_THEN_it_is_persisted_with_a_derived_slug(): void
    {
        $repository = new EloquentGenreRepository();

        $saved = $repository->save(new Genre(id: null, name: 'Rock Nacional', slug: null, isActive: true));

        $this->assertNotNull($saved->id);
        $this->assertSame('rock-nacional', $saved->slug);
        $this->assertDatabaseHas('genres', ['id' => $saved->id, 'slug' => 'rock-nacional']);
    }

    public function test_GIVEN_existing_genres_WHEN_finding_all_THEN_both_active_and_inactive_are_returned(): void
    {
        GenreModel::factory()->create(['name' => 'Rock', 'is_active' => true]);
        GenreModel::factory()->create(['name' => 'Forró', 'is_active' => false]);

        $repository = new EloquentGenreRepository();

        $genres = $repository->findAll();

        $this->assertCount(2, $genres);
    }

    public function test_GIVEN_an_existing_genre_id_WHEN_finding_by_id_THEN_it_is_returned(): void
    {
        $model = GenreModel::factory()->create(['name' => 'Rock']);

        $repository = new EloquentGenreRepository();

        $genre = $repository->findById($model->id);

        $this->assertNotNull($genre);
        $this->assertSame('Rock', $genre->name);
    }

    public function test_GIVEN_a_non_existent_genre_id_WHEN_finding_by_id_THEN_it_returns_null(): void
    {
        $repository = new EloquentGenreRepository();

        $this->assertNull($repository->findById(999999));
    }
}

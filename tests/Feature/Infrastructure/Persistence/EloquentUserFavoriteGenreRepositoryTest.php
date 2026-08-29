<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentUserFavoriteGenreRepository;
use Tests\TestCase;

class EloquentUserFavoriteGenreRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function genre(string $name): int
    {
        return (int) DB::table('genres')->insertGetId([
            'name' => $name,
            'slug' => str($name)->slug(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_GIVEN_no_favorite_genres_WHEN_listing_for_user_THEN_an_empty_list_is_returned(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentUserFavoriteGenreRepository();

        $this->assertSame([], $repository->listForUser($user->id));
    }

    public function test_GIVEN_a_set_of_genre_ids_WHEN_replacing_for_user_THEN_they_are_persisted_and_listable(): void
    {
        $user = UserModel::factory()->create();
        $rock = $this->genre('Rock');
        $mpb = $this->genre('MPB');
        $repository = new EloquentUserFavoriteGenreRepository();

        $repository->replaceForUser($user->id, [$rock, $mpb]);

        $this->assertEqualsCanonicalizing([$rock, $mpb], $repository->listForUser($user->id));
    }

    public function test_GIVEN_an_existing_favorite_genre_set_WHEN_replacing_it_with_a_new_set_THEN_the_old_set_is_gone(): void
    {
        $user = UserModel::factory()->create();
        $rock = $this->genre('Rock');
        $mpb = $this->genre('MPB');
        $repository = new EloquentUserFavoriteGenreRepository();

        $repository->replaceForUser($user->id, [$rock]);
        $repository->replaceForUser($user->id, [$mpb]);

        $this->assertSame([$mpb], $repository->listForUser($user->id));
    }
}

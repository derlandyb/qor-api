<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\User\UserFavoriteGenreRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\UserFavoriteGenreModel;

class EloquentUserFavoriteGenreRepository implements UserFavoriteGenreRepository
{
    public function replaceForUser(int $userId, array $genreIds): void
    {
        UserFavoriteGenreModel::where('user_id', $userId)->delete();

        foreach ($genreIds as $genreId) {
            UserFavoriteGenreModel::create([
                'user_id' => $userId,
                'genre_id' => $genreId,
                'created_at' => now(),
            ]);
        }
    }

    public function listForUser(int $userId): array
    {
        /** @var list<int> $ids */
        $ids = UserFavoriteGenreModel::where('user_id', $userId)->pluck('genre_id')->all();

        return $ids;
    }
}

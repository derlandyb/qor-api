<?php

namespace QOR\App\Domain\User;

interface UserFavoriteGenreRepository
{
    /**
     * Replaces the fan's full set of favorite genres with $genreIds (AUTH-22).
     *
     * @param list<int> $genreIds
     */
    public function replaceForUser(int $userId, array $genreIds): void;

    /**
     * @return list<int>
     */
    public function listForUser(int $userId): array;
}

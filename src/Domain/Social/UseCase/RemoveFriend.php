<?php

namespace QOR\App\Domain\Social\UseCase;

use QOR\App\Domain\Social\FriendshipRepository;

final class RemoveFriend
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
    ) {
    }

    public function execute(int $userId, int $friendUserId): void
    {
        $this->friendships->remove($userId, $friendUserId);
    }
}

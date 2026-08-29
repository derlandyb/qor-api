<?php

namespace QOR\App\Domain\Social\UseCase;

use QOR\App\Domain\Social\FriendPage;
use QOR\App\Domain\Social\FriendshipRepository;

final class ListFriends
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
    ) {
    }

    public function execute(int $userId, ?string $cursor): FriendPage
    {
        return $this->friendships->listAccepted($userId, $cursor);
    }
}

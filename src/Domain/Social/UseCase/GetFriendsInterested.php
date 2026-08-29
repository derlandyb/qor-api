<?php

namespace QOR\App\Domain\Social\UseCase;

use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;

final class GetFriendsInterested
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
        private readonly FavoriteRepository $favorites,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @return list<User>
     */
    public function execute(int $userId, int $eventId): array
    {
        $friends = $this->friendships->listAccepted($userId, null)->items;

        $interested = [];

        foreach ($friends as $friendship) {
            $friendUserId = $this->otherUserId($friendship, $userId);

            if (! $this->favorites->hasUserFavorited($friendUserId, $eventId)) {
                continue;
            }

            $user = $this->users->findById($friendUserId);

            if ($user !== null) {
                $interested[] = $user;
            }
        }

        return $interested;
    }

    private function otherUserId(Friendship $friendship, int $userId): int
    {
        return $friendship->requesterId === $userId ? $friendship->recipientId : $friendship->requesterId;
    }
}

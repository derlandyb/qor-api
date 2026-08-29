<?php

namespace QOR\App\Domain\Social\UseCase;

use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\Social\FeedPage;
use QOR\App\Domain\Social\FriendshipRepository;

final class GetSocialFeed
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
        private readonly FavoriteRepository $favorites,
    ) {
    }

    /**
     * Simple P3 implementation: one page of accepted friends, each friend's
     * currently-favorited Published events (already status-filtered by
     * FavoriteRepository::listForUser). Feed pagination follows the friend
     * list's own cursor.
     */
    public function execute(int $userId, ?string $cursor): FeedPage
    {
        $friendPage = $this->friendships->listAccepted($userId, $cursor);

        $items = [];

        foreach ($friendPage->items as $friendship) {
            $friendUserId = $friendship->requesterId === $userId ? $friendship->recipientId : $friendship->requesterId;

            foreach ($this->favorites->listForUser($friendUserId, null)->items as $event) {
                $items[] = ['event' => $event, 'friendUserId' => $friendUserId];
            }
        }

        return new FeedPage(items: $items, nextCursor: $friendPage->nextCursor);
    }
}

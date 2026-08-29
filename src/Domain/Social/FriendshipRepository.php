<?php

namespace QOR\App\Domain\Social;

interface FriendshipRepository
{
    public function create(int $requesterId, int $recipientId): Friendship;

    public function accept(int $friendshipId): Friendship;

    public function reject(int $friendshipId): void;

    public function remove(int $userId, int $friendUserId): void;

    /**
     * Accepted friendships involving $userId, cursor-paginated per
     * `config('qor.pagination.public_page_size')`.
     */
    public function listAccepted(int $userId, ?string $cursor): FriendPage;

    /**
     * Pending requests where $userId is the recipient (incoming only, FAV-11).
     *
     * @return list<Friendship>
     */
    public function listIncomingPending(int $userId): array;

    /**
     * The single row (regardless of direction) between the two users, if any.
     */
    public function findBetween(int $userIdA, int $userIdB): ?Friendship;
}

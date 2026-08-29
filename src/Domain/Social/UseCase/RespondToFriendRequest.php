<?php

namespace QOR\App\Domain\Social\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;

final class RespondToFriendRequest
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
    ) {
    }

    public function accept(int $requestId, int $respondingUserId): Friendship
    {
        $this->assertRecipient($requestId, $respondingUserId);

        return $this->friendships->accept($requestId);
    }

    public function reject(int $requestId, int $respondingUserId): void
    {
        $this->assertRecipient($requestId, $respondingUserId);

        $this->friendships->reject($requestId);
    }

    /**
     * @return list<Friendship>
     */
    public function listIncoming(int $userId): array
    {
        return $this->friendships->listIncomingPending($userId);
    }

    private function assertRecipient(int $requestId, int $respondingUserId): void
    {
        $friendship = $this->friendships->findById($requestId);

        if ($friendship === null) {
            throw new InvalidArgumentException('Solicitação de amizade não encontrada.');
        }

        if ($friendship->recipientId !== $respondingUserId) {
            throw new InvalidArgumentException('Você não pode responder a esta solicitação de amizade.');
        }
    }
}

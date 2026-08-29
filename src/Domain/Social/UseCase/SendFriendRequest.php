<?php

namespace QOR\App\Domain\Social\UseCase;

use DomainException;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;

final class SendFriendRequest
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
    ) {
    }

    public function execute(int $requesterId, int $recipientId): Friendship
    {
        if ($requesterId === $recipientId) {
            throw new DomainException('Não é possível enviar uma solicitação de amizade para si mesmo.');
        }

        $existing = $this->friendships->findBetween($requesterId, $recipientId);

        if ($existing === null) {
            return $this->friendships->create($requesterId, $recipientId);
        }

        if ($existing->status === FriendshipStatus::Accepted) {
            throw new DomainException('Vocês já são amigos.');
        }

        if ($existing->requesterId === $requesterId) {
            return $existing;
        }

        return $this->friendships->accept((int) $existing->id);
    }
}

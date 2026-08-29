<?php

namespace QOR\App\Domain\Social;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Social\Enum\FriendshipStatus;

final class Friendship
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $requesterId,
        public readonly int $recipientId,
        public readonly FriendshipStatus $status = FriendshipStatus::Pending,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {
        if ($this->requesterId <= 0 || $this->recipientId <= 0) {
            throw new InvalidArgumentException('O ID do usuário deve ser válido.');
        }

        if ($this->requesterId === $this->recipientId) {
            throw new InvalidArgumentException('Não é possível enviar uma solicitação de amizade para si mesmo.');
        }
    }

    public function accept(): self
    {
        return new self(
            id: $this->id,
            requesterId: $this->requesterId,
            recipientId: $this->recipientId,
            status: FriendshipStatus::Accepted,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }
}

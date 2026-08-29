<?php

namespace QOR\App\Domain\Social;

use DateTimeImmutable;
use InvalidArgumentException;

final class Favorite
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly int $eventId,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('O ID do usuário deve ser válido.');
        }

        if ($this->eventId <= 0) {
            throw new InvalidArgumentException('O ID do evento deve ser válido.');
        }
    }
}

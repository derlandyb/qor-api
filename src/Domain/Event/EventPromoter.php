<?php

namespace QOR\App\Domain\Event;

use DateTimeImmutable;

final class EventPromoter
{
    public function __construct(
        public readonly int $eventId,
        public readonly int $promoterId,
        public readonly DateTimeImmutable $taggedAt,
    ) {
    }
}

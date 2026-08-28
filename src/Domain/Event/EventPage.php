<?php

namespace QOR\App\Domain\Event;

final class EventPage
{
    /**
     * @param list<Event> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {
    }
}

<?php

namespace QOR\App\Domain\Social;

use QOR\App\Domain\Event\Event;

final class FavoritePage
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

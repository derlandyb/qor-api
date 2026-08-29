<?php

namespace QOR\App\Domain\Social;

final class FeedPage
{
    /**
     * @param list<array{event: \QOR\App\Domain\Event\Event, friendUserId: int}> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {
    }
}

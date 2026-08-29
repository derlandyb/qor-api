<?php

namespace QOR\App\Domain\Social;

final class FriendPage
{
    /**
     * @param list<Friendship> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {
    }
}

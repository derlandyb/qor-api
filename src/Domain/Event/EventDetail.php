<?php

namespace QOR\App\Domain\Event;

use QOR\App\Domain\Promoter\Promoter;

final class EventDetail
{
    /**
     * @param list<Promoter> $taggedPromoters
     */
    public function __construct(
        public readonly Event $event,
        public readonly array $taggedPromoters,
    ) {
    }
}

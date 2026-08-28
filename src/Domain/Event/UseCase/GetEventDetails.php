<?php

namespace QOR\App\Domain\Event\UseCase;

use QOR\App\Domain\Event\EventDetail;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\PromoterRepository;

final class GetEventDetails
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly PromoterRepository $promoters,
    ) {
    }

    /**
     * Returns null only when no event exists for $eventId. A Cancelled/Encerrado
     * event is still returned (not filtered out) so a stale/direct link renders
     * its status banner instead of a 404 (DISC-07..13).
     */
    public function execute(int $eventId): ?EventDetail
    {
        $event = $this->events->findById($eventId);

        if ($event === null) {
            return null;
        }

        return new EventDetail(
            event: $event,
            taggedPromoters: $this->promoters->findTaggedForEvent($eventId),
        );
    }
}

<?php

namespace QOR\App\Domain\Event\UseCase;

use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\MapBounds;
use QOR\App\Domain\Shared\Enum\City;

final class GetMapEvents
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
    }

    /**
     * @return list<Event>
     */
    public function execute(?MapBounds $bounds, ?City $city): array
    {
        return $this->events->findMapEvents($bounds, $city);
    }
}

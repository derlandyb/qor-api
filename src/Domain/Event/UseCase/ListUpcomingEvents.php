<?php

namespace QOR\App\Domain\Event\UseCase;

use QOR\App\Domain\Event\EventPage;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Shared\Enum\City;

final class ListUpcomingEvents
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
    }

    public function execute(?City $city = null, ?int $genreId = null, ?string $cursor = null): EventPage
    {
        return $this->events->findUpcoming($city, $genreId, $cursor);
    }
}

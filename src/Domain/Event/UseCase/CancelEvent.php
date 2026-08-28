<?php

namespace QOR\App\Domain\Event\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Venue\Venue;

final class CancelEvent
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
    }

    public function execute(int $eventId, Venue|Promoter $organizer): Event
    {
        $event = $this->events->findById($eventId);

        if ($event === null || $event->createdById !== $organizer->id) {
            throw new InvalidArgumentException('Evento não encontrado.');
        }

        $event = $event->transitionTo(EventStatus::Cancelled);

        return $this->events->save($event);
    }
}

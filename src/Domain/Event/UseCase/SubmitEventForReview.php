<?php

namespace QOR\App\Domain\Event\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Venue\Venue;

final class SubmitEventForReview
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
    }

    public function execute(int $eventId, Venue|Promoter $organizer): Event
    {
        $event = $this->events->findById($eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Evento não encontrado.');
        }

        if (! $organizer->canPublish()) {
            throw new InvalidArgumentException('Sua conta ainda não foi aprovada.');
        }

        $event = $event->transitionTo(EventStatus::PendingReview);

        return $this->events->save($event);
    }
}

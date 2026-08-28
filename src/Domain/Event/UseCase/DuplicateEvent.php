<?php

namespace QOR\App\Domain\Event\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Venue\Venue;

final class DuplicateEvent
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
    }

    public function execute(int $eventId, Venue|Promoter $organizer, DateTimeImmutable $startsAt): Event
    {
        $event = $this->events->findById($eventId);

        if ($event === null || $event->createdById !== $organizer->id) {
            throw new InvalidArgumentException('Evento não encontrado.');
        }

        $duplicate = new Event(
            id: null,
            createdByType: $event->createdByType,
            createdById: $event->createdById,
            title: $event->title,
            description: $event->description,
            startsAt: $startsAt,
            city: $event->city,
            genreId: $event->genreId,
            isFree: $event->isFree,
            status: EventStatus::Draft,
            coverImageUrl: $event->coverImageUrl,
            address: $event->address,
            ticketUrl: $event->ticketUrl,
            capacity: $event->capacity,
            ageRating: $event->ageRating,
            notes: $event->notes,
            rejectionFeedback: null,
        );

        return $this->events->save($duplicate);
    }
}

<?php

namespace QOR\App\Domain\Event;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Shared\Enum\City;

final class Event
{
    /** @var array<string, list<string>> */
    private const LEGAL_TRANSITIONS = [
        'draft' => ['pending_review'],
        'pending_review' => ['published', 'draft', 'encerrado', 'cancelled'],
        'published' => ['cancelled', 'encerrado'],
        'cancelled' => [],
        'encerrado' => [],
    ];

    public function __construct(
        public readonly ?int $id,
        public readonly EventCreatedByType $createdByType,
        public readonly int $createdById,
        public readonly string $title,
        public readonly string $description,
        public readonly DateTimeImmutable $startsAt,
        public readonly City $city,
        public readonly int $genreId,
        public readonly bool $isFree,
        public readonly EventStatus $status = EventStatus::Draft,
        public readonly ?string $coverImageUrl = null,
        public readonly ?string $address = null,
        public readonly ?string $ticketUrl = null,
        public readonly ?int $capacity = null,
        public readonly ?string $ageRating = null,
        public readonly ?string $notes = null,
        public readonly ?string $rejectionFeedback = null,
    ) {
        if ($this->title === '') {
            throw new InvalidArgumentException('O título do evento não pode ser vazio.');
        }

        if (! $this->isFree && $this->ticketUrl === null) {
            throw new InvalidArgumentException('Eventos pagos precisam de um link de ingresso.');
        }
    }

    /**
     * Returns a copy of this event transitioned to $newStatus, or throws if the
     * transition is not legal per the event state machine (ARCHITECTURE.md §5).
     */
    public function transitionTo(EventStatus $newStatus, ?string $rejectionFeedback = null): self
    {
        $allowed = self::LEGAL_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus->value, $allowed, true)) {
            throw new DomainException(
                "Transição inválida de '{$this->status->value}' para '{$newStatus->value}'."
            );
        }

        return new self(
            id: $this->id,
            createdByType: $this->createdByType,
            createdById: $this->createdById,
            title: $this->title,
            description: $this->description,
            coverImageUrl: $this->coverImageUrl,
            startsAt: $this->startsAt,
            city: $this->city,
            genreId: $this->genreId,
            isFree: $this->isFree,
            status: $newStatus,
            address: $this->address,
            ticketUrl: $this->ticketUrl,
            capacity: $this->capacity,
            ageRating: $this->ageRating,
            notes: $this->notes,
            rejectionFeedback: $newStatus === EventStatus::Draft ? $rejectionFeedback : null,
        );
    }
}

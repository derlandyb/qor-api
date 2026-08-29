<?php

namespace QOR\App\Domain\Event\UseCase;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\DomainEvent\EventChanged;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\DomainEventPublisher;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\Venue;

final class EditEvent
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly FileUploadPort $fileUpload,
        private readonly PromoterRepository $promoters,
        private readonly DomainEventPublisher $domainEvents,
    ) {
    }

    /**
     * @param ?list<int> $promoterIds null = tagging untouched; array = full replace
     */
    public function execute(
        int $eventId,
        Venue|Promoter $organizer,
        ?string $title = null,
        ?string $description = null,
        ?DateTimeImmutable $startsAt = null,
        ?City $city = null,
        ?int $genreId = null,
        ?bool $isFree = null,
        ?string $address = null,
        ?UploadableFile $coverImage = null,
        ?string $ticketUrl = null,
        ?int $capacity = null,
        ?string $ageRating = null,
        ?string $notes = null,
        ?array $promoterIds = null,
    ): Event {
        $event = $this->events->findById($eventId);

        if ($event === null || $event->createdById !== $organizer->id) {
            throw new InvalidArgumentException('Evento não encontrado.');
        }

        if ($promoterIds !== null && $organizer instanceof Venue) {
            $this->assertPromotersApproved($promoterIds);
        }

        $coverImageUrl = $event->coverImageUrl;
        if ($coverImage !== null) {
            $coverImageUrl = $this->fileUpload->upload($coverImage, 'events/covers');
        }

        if ($event->status === EventStatus::Draft) {
            $updated = new Event(
                id: $event->id,
                createdByType: $event->createdByType,
                createdById: $event->createdById,
                title: $title ?? $event->title,
                description: $description ?? $event->description,
                startsAt: $startsAt ?? $event->startsAt,
                city: $city ?? $event->city,
                genreId: $genreId ?? $event->genreId,
                isFree: $isFree ?? $event->isFree,
                status: EventStatus::Draft,
                coverImageUrl: $coverImageUrl,
                address: $address ?? $event->address,
                ticketUrl: $ticketUrl ?? $event->ticketUrl,
                capacity: $capacity ?? $event->capacity,
                ageRating: $ageRating ?? $event->ageRating,
                notes: $notes ?? $event->notes,
                rejectionFeedback: $event->rejectionFeedback,
            );

            $savedEvent = $this->events->save($updated);
            $this->tagPromoters($organizer, $savedEvent, $promoterIds);

            return $savedEvent;
        }

        if ($event->status === EventStatus::Published) {
            $onlyAllowedFieldsChanged = ($title === null || $title === $event->title)
                && ($startsAt === null || $startsAt == $event->startsAt)
                && ($city === null || $city === $event->city)
                && ($genreId === null || $genreId === $event->genreId)
                && ($isFree === null || $isFree === $event->isFree)
                && ($address === null || $address === $event->address)
                && ($ticketUrl === null || $ticketUrl === $event->ticketUrl)
                && ($capacity === null || $capacity === $event->capacity)
                && ($ageRating === null || $ageRating === $event->ageRating)
                && ($notes === null || $notes === $event->notes);

            if (! $onlyAllowedFieldsChanged) {
                throw new DomainException('Apenas descrição e imagem podem ser editados após a publicação.');
            }

            $updated = new Event(
                id: $event->id,
                createdByType: $event->createdByType,
                createdById: $event->createdById,
                title: $event->title,
                description: $description ?? $event->description,
                startsAt: $event->startsAt,
                city: $event->city,
                genreId: $event->genreId,
                isFree: $event->isFree,
                status: EventStatus::Published,
                coverImageUrl: $coverImageUrl,
                address: $event->address,
                ticketUrl: $event->ticketUrl,
                capacity: $event->capacity,
                ageRating: $event->ageRating,
                notes: $event->notes,
                rejectionFeedback: $event->rejectionFeedback,
            );

            $savedEvent = $this->events->save($updated);
            $this->tagPromoters($organizer, $savedEvent, $promoterIds);

            // NOTIF-05: only the two fields a Published event can actually
            // change (description/cover) make this "material" — a no-op
            // edit call (nothing differs from the persisted event) must not
            // spam every favoriting fan.
            $descriptionChanged = $description !== null && $description !== $event->description;
            $coverChanged = $coverImage !== null && $coverImageUrl !== $event->coverImageUrl;

            if (($descriptionChanged || $coverChanged) && $savedEvent->id !== null) {
                $this->domainEvents->publish(new EventChanged($savedEvent->id));
            }

            return $savedEvent;
        }

        throw new DomainException('Este evento não pode ser editado no status atual.');
    }

    /**
     * @param ?list<int> $promoterIds
     */
    private function tagPromoters(Venue|Promoter $organizer, Event $event, ?array $promoterIds): void
    {
        if ($promoterIds === null || ! $organizer instanceof Venue || $event->id === null) {
            return;
        }

        $this->promoters->tagEvent($event->id, $promoterIds);
    }

    /**
     * @param list<int> $promoterIds
     */
    private function assertPromotersApproved(array $promoterIds): void
    {
        foreach ($promoterIds as $promoterId) {
            $promoter = $this->promoters->findById($promoterId);
            if ($promoter === null || $promoter->approvalStatus !== ApprovalStatus::Approved) {
                throw new InvalidArgumentException('Promotor inválido ou não aprovado.');
            }
        }
    }
}

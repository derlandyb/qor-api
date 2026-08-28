<?php

namespace QOR\App\Domain\Event\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\Venue;

final class CreateEvent
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly FileUploadPort $fileUpload,
        private readonly PromoterRepository $promoters,
    ) {
    }

    /**
     * @param list<int> $promoterIds
     */
    public function execute(
        EventCreatedByType $createdByType,
        Venue|Promoter $organizer,
        string $title,
        string $description,
        DateTimeImmutable $startsAt,
        City $city,
        int $genreId,
        bool $isFree,
        ?string $address = null,
        ?UploadableFile $coverImage = null,
        ?string $ticketUrl = null,
        ?int $capacity = null,
        ?string $ageRating = null,
        ?string $notes = null,
        array $promoterIds = [],
    ): Event {
        if (! $organizer->canPublish()) {
            throw new InvalidArgumentException('Sua conta ainda não foi aprovada.');
        }

        if ($address === null) {
            if ($organizer instanceof Venue) {
                $address = $organizer->address;
            } else {
                throw new InvalidArgumentException('O endereço é obrigatório.');
            }
        }

        $coverImageUrl = null;
        if ($coverImage !== null) {
            $coverImageUrl = $this->fileUpload->upload($coverImage, 'events/covers');
        }

        $createdById = $organizer->id;
        if ($createdById === null) {
            throw new InvalidArgumentException('O organizador precisa estar cadastrado.');
        }

        if ($organizer instanceof Venue && $promoterIds !== []) {
            $this->assertPromotersApproved($promoterIds);
        }

        $event = new Event(
            id: null,
            createdByType: $createdByType,
            createdById: $createdById,
            title: $title,
            description: $description,
            startsAt: $startsAt,
            city: $city,
            genreId: $genreId,
            isFree: $isFree,
            coverImageUrl: $coverImageUrl,
            address: $address,
            ticketUrl: $ticketUrl,
            capacity: $capacity,
            ageRating: $ageRating,
            notes: $notes,
        );

        $savedEvent = $this->events->save($event);

        if ($organizer instanceof Venue && $promoterIds !== [] && $savedEvent->id !== null) {
            $this->promoters->tagEvent($savedEvent->id, $promoterIds);
        }

        return $savedEvent;
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

<?php

namespace QOR\App\Domain\Event\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\Venue;

final class CreateEvent
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly FileUploadPort $fileUpload,
    ) {
    }

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

        return $this->events->save($event);
    }
}

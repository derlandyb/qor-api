<?php

namespace Tests\Unit\Domain\Event\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\CreateEvent;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\Venue;

class CreateEventTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function approvedVenue(): Venue
    {
        return new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Casa de Show',
            description: 'Um lugar legal',
            address: 'Rua das Flores, 123',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@casa.com',
            approvalStatus: ApprovalStatus::Approved,
        );
    }

    private function approvedPromoter(): Promoter
    {
        return new Promoter(
            id: 2,
            userId: 20,
            name: 'Produtora XYZ',
            contactPhone: '27999999999',
            contactEmail: 'contato@produtora.com',
            approvalStatus: ApprovalStatus::Approved,
        );
    }

    public function test_GIVEN_a_venue_organizer_with_no_address_WHEN_creating_an_event_THEN_it_defaults_to_the_venues_registered_address(): void
    {
        $venue = $this->approvedVenue();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->withArgs(fn (Event $event) => $event->address === $venue->address)
            ->andReturnUsing(fn (Event $event) => $event);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $event = $useCase->execute(
            createdByType: EventCreatedByType::VenueAdmin,
            organizer: $venue,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
        );

        $this->assertSame($venue->address, $event->address);
    }

    public function test_GIVEN_a_promoter_organizer_with_no_address_WHEN_creating_an_event_THEN_it_is_rejected(): void
    {
        $promoter = $this->approvedPromoter();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O endereço é obrigatório.');

        $useCase->execute(
            createdByType: EventCreatedByType::Promoter,
            organizer: $promoter,
            title: 'Festa na Praia',
            description: 'Uma festa incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
        );
    }

    public function test_GIVEN_a_promoter_organizer_with_an_explicit_address_WHEN_creating_an_event_THEN_it_succeeds(): void
    {
        $promoter = $this->approvedPromoter();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->withArgs(fn (Event $event) => $event->address === 'Praia de Camburi')
            ->andReturnUsing(fn (Event $event) => $event);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $event = $useCase->execute(
            createdByType: EventCreatedByType::Promoter,
            organizer: $promoter,
            title: 'Festa na Praia',
            description: 'Uma festa incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            address: 'Praia de Camburi',
        );

        $this->assertSame('Praia de Camburi', $event->address);
    }

    public function test_GIVEN_an_unapproved_organizer_WHEN_creating_an_event_THEN_it_is_blocked_before_any_repository_write(): void
    {
        $venue = new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Casa de Show',
            description: 'Um lugar legal',
            address: 'Rua das Flores, 123',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@casa.com',
            approvalStatus: ApprovalStatus::PendingApproval,
        );

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $fileUpload->shouldNotReceive('upload');

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sua conta ainda não foi aprovada.');

        $useCase->execute(
            createdByType: EventCreatedByType::VenueAdmin,
            organizer: $venue,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
        );
    }

    public function test_GIVEN_a_cover_image_WHEN_creating_an_event_THEN_it_is_uploaded_and_the_url_is_set(): void
    {
        $venue = $this->approvedVenue();
        $coverImage = new UploadableFile(
            path: '/tmp/cover.jpg',
            originalName: 'cover.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: 1024,
        );

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->andReturnUsing(fn (Event $event) => $event);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $fileUpload->shouldReceive('upload')
            ->once()
            ->with($coverImage, 'events/covers')
            ->andReturn('https://cdn.qor.com/events/covers/cover.jpg');

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $event = $useCase->execute(
            createdByType: EventCreatedByType::VenueAdmin,
            organizer: $venue,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            coverImage: $coverImage,
        );

        $this->assertSame('https://cdn.qor.com/events/covers/cover.jpg', $event->coverImageUrl);
    }

    public function test_GIVEN_no_cover_image_WHEN_creating_an_event_THEN_the_cover_image_url_stays_null(): void
    {
        $venue = $this->approvedVenue();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->andReturnUsing(fn (Event $event) => $event);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $fileUpload->shouldNotReceive('upload');

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $event = $useCase->execute(
            createdByType: EventCreatedByType::VenueAdmin,
            organizer: $venue,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
        );

        $this->assertNull($event->coverImageUrl);
    }

    public function test_GIVEN_a_venue_organizer_and_valid_approved_promoter_ids_WHEN_creating_an_event_THEN_they_are_tagged(): void
    {
        $venue = $this->approvedVenue();
        $approvedPromoter = $this->approvedPromoter();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->andReturnUsing(fn (Event $event) => new Event(
                id: 99,
                createdByType: $event->createdByType,
                createdById: $event->createdById,
                title: $event->title,
                description: $event->description,
                startsAt: $event->startsAt,
                city: $event->city,
                genreId: $event->genreId,
                isFree: $event->isFree,
                address: $event->address,
            ));

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(2)->andReturn($approvedPromoter);
        $promoters->shouldReceive('tagEvent')->once()->with(99, [2]);

        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $useCase->execute(
            createdByType: EventCreatedByType::VenueAdmin,
            organizer: $venue,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            promoterIds: [2],
        );
    }

    public function test_GIVEN_a_venue_organizer_and_an_unapproved_promoter_id_WHEN_creating_an_event_THEN_it_throws_and_does_not_tag(): void
    {
        $venue = $this->approvedVenue();
        $unapprovedPromoter = new Promoter(
            id: 3,
            userId: 30,
            name: 'Produtora Pendente',
            contactPhone: '27999999999',
            contactEmail: 'pendente@produtora.com',
            approvalStatus: ApprovalStatus::PendingApproval,
        );

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(3)->andReturn($unapprovedPromoter);
        $promoters->shouldNotReceive('tagEvent');

        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotor inválido ou não aprovado.');

        $useCase->execute(
            createdByType: EventCreatedByType::VenueAdmin,
            organizer: $venue,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            promoterIds: [3],
        );
    }

    public function test_GIVEN_a_promoter_organizer_WHEN_creating_an_event_with_promoter_ids_THEN_they_are_silently_ignored(): void
    {
        $promoterOrganizer = $this->approvedPromoter();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->andReturnUsing(fn (Event $event) => new Event(
                id: 99,
                createdByType: $event->createdByType,
                createdById: $event->createdById,
                title: $event->title,
                description: $event->description,
                startsAt: $event->startsAt,
                city: $event->city,
                genreId: $event->genreId,
                isFree: $event->isFree,
                address: $event->address,
            ));

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldNotReceive('findById');
        $promoters->shouldNotReceive('tagEvent');

        $useCase = new CreateEvent($repository, $fileUpload, $promoters);

        $useCase->execute(
            createdByType: EventCreatedByType::Promoter,
            organizer: $promoterOrganizer,
            title: 'Festa na Praia',
            description: 'Uma festa incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            address: 'Praia de Camburi',
            promoterIds: [2],
        );
    }
}

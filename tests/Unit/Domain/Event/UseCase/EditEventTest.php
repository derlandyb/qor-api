<?php

namespace Tests\Unit\Domain\Event\UseCase;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\EditEvent;
use QOR\App\Domain\Event\DomainEvent\EventChanged;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\DomainEventPublisher;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Venue\Venue;

class EditEventTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * A permissive domain-event publisher double for tests that don't
     * assert on event emission (each assertion-relevant test builds its
     * own stricter expectation instead).
     */
    private function domainEvents(): DomainEventPublisher
    {
        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldReceive('publish')->zeroOrMoreTimes();

        return $domainEvents;
    }

    private function approvedVenue(int $id = 1): Venue
    {
        return new Venue(
            id: $id,
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

    private function makeEvent(EventStatus $status, int $createdById = 1, string $title = 'Show da Banda X'): Event
    {
        return new Event(
            id: 1,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: $createdById,
            title: $title,
            description: 'Descrição original.',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 2,
            isFree: true,
            status: $status,
        );
    }

    public function test_GIVEN_a_draft_event_owned_by_the_organizer_WHEN_editing_THEN_provided_fields_are_applied_and_status_stays_draft(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Draft, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Event $e) => $e->title === 'Novo Título' && $e->status === EventStatus::Draft))
            ->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $result = $useCase->execute(eventId: 1, organizer: $venue, title: 'Novo Título');

        $this->assertSame('Novo Título', $result->title);
    }

    public function test_GIVEN_a_published_event_WHEN_editing_only_description_THEN_it_is_allowed(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Event $e) => $e->description === 'Nova descrição.' && $e->status === EventStatus::Published))
            ->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $result = $useCase->execute(eventId: 1, organizer: $venue, description: 'Nova descrição.');

        $this->assertSame('Nova descrição.', $result->description);
    }

    public function test_GIVEN_a_published_event_WHEN_editing_the_title_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Apenas descrição e imagem podem ser editados após a publicação.');

        $useCase->execute(eventId: 1, organizer: $venue, title: 'Título Novo');
    }

    public function test_GIVEN_an_event_not_owned_by_the_organizer_WHEN_editing_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue(id: 1);
        $event = $this->makeEvent(EventStatus::Draft, createdById: 999);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(eventId: 1, organizer: $venue, title: 'Título Novo');
    }

    public function test_GIVEN_an_event_in_pending_review_WHEN_editing_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::PendingReview, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Este evento não pode ser editado no status atual.');

        $useCase->execute(eventId: 1, organizer: $venue, title: 'Título Novo');
    }

    public function test_GIVEN_the_event_does_not_exist_WHEN_editing_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(404)->andReturn(null);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(eventId: 404, organizer: $venue, title: 'Título Novo');
    }

    private function approvedPromoter(int $id = 2): Promoter
    {
        return new Promoter(
            id: $id,
            userId: 20,
            name: 'Produtora XYZ',
            contactPhone: '27999999999',
            contactEmail: 'contato@produtora.com',
            approvalStatus: ApprovalStatus::Approved,
        );
    }

    public function test_GIVEN_a_draft_event_and_valid_approved_promoter_ids_WHEN_editing_THEN_they_are_tagged(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Draft, createdById: 1);
        $approvedPromoter = $this->approvedPromoter();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(2)->andReturn($approvedPromoter);
        $promoters->shouldReceive('tagEvent')->once()->with(1, [2]);

        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $useCase->execute(eventId: 1, organizer: $venue, promoterIds: [2]);
    }

    public function test_GIVEN_a_published_event_and_valid_promoter_ids_WHEN_editing_THEN_they_are_tagged(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);
        $approvedPromoter = $this->approvedPromoter();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(2)->andReturn($approvedPromoter);
        $promoters->shouldReceive('tagEvent')->once()->with(1, [2]);

        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $useCase->execute(eventId: 1, organizer: $venue, promoterIds: [2]);
    }

    public function test_GIVEN_an_unapproved_promoter_id_WHEN_editing_THEN_it_throws_and_does_not_tag(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Draft, createdById: 1);
        $unapprovedPromoter = new Promoter(
            id: 3,
            userId: 30,
            name: 'Produtora Pendente',
            contactPhone: '27999999999',
            contactEmail: 'pendente@produtora.com',
            approvalStatus: ApprovalStatus::PendingApproval,
        );

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(3)->andReturn($unapprovedPromoter);
        $promoters->shouldNotReceive('tagEvent');

        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotor inválido ou não aprovado.');

        $useCase->execute(eventId: 1, organizer: $venue, promoterIds: [3]);
    }

    public function test_GIVEN_a_published_event_WHEN_editing_the_description_THEN_event_changed_fires_with_the_event_id(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $promoters = Mockery::mock(PromoterRepository::class);

        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(fn (EventChanged $e) => $e->eventId === 1));

        $useCase = new EditEvent($repository, $fileUpload, $promoters, $domainEvents);

        $useCase->execute(eventId: 1, organizer: $venue, description: 'Nova descrição.');
    }

    public function test_GIVEN_a_draft_event_WHEN_editing_it_THEN_event_changed_does_not_fire(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Draft, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $promoters = Mockery::mock(PromoterRepository::class);

        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldNotReceive('publish');

        $useCase = new EditEvent($repository, $fileUpload, $promoters, $domainEvents);

        $useCase->execute(eventId: 1, organizer: $venue, title: 'Novo Título');
    }

    public function test_GIVEN_a_published_event_WHEN_editing_with_no_actual_field_changes_THEN_event_changed_does_not_fire(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $promoters = Mockery::mock(PromoterRepository::class);

        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldNotReceive('publish');

        $useCase = new EditEvent($repository, $fileUpload, $promoters, $domainEvents);

        // Same description as the persisted event — no material change.
        $useCase->execute(eventId: 1, organizer: $venue, description: 'Descrição original.');
    }

    public function test_GIVEN_promoter_ids_not_provided_WHEN_editing_THEN_existing_tags_are_left_untouched(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Draft, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldNotReceive('findById');
        $promoters->shouldNotReceive('tagEvent');

        $useCase = new EditEvent($repository, $fileUpload, $promoters, $this->domainEvents());

        $useCase->execute(eventId: 1, organizer: $venue, title: 'Novo Título');
    }
}

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
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Venue\Venue;

class EditEventTest extends TestCase
{
    use MockeryPHPUnitIntegration;

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

        $useCase = new EditEvent($repository, $fileUpload);

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

        $useCase = new EditEvent($repository, $fileUpload);

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

        $useCase = new EditEvent($repository, $fileUpload);

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

        $useCase = new EditEvent($repository, $fileUpload);

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

        $useCase = new EditEvent($repository, $fileUpload);

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

        $useCase = new EditEvent($repository, $fileUpload);

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(eventId: 404, organizer: $venue, title: 'Título Novo');
    }
}

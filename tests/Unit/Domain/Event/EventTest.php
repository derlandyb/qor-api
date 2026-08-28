<?php

namespace Tests\Unit\Domain\Event;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Shared\Enum\City;

class EventTest extends TestCase
{
    private function makeEvent(bool $isFree = true, ?string $ticketUrl = null): Event
    {
        return new Event(
            id: 1,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 10,
            title: 'Noite do Rock',
            description: 'Show ao vivo.',
            coverImageUrl: 'https://example.com/cover.jpg',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: $isFree,
            ticketUrl: $ticketUrl,
        );
    }

    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_entity_starts_as_draft(): void
    {
        $event = $this->makeEvent();

        $this->assertSame(EventStatus::Draft, $event->status);
    }

    public function test_GIVEN_a_paid_event_without_a_ticket_url_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeEvent(isFree: false, ticketUrl: null);
    }

    public function test_GIVEN_a_draft_event_WHEN_submitting_for_review_THEN_it_transitions_to_pending_review(): void
    {
        $event = $this->makeEvent()->transitionTo(EventStatus::PendingReview);

        $this->assertSame(EventStatus::PendingReview, $event->status);
    }

    public function test_GIVEN_a_pending_review_event_WHEN_approved_THEN_it_transitions_to_published(): void
    {
        $event = $this->makeEvent()
            ->transitionTo(EventStatus::PendingReview)
            ->transitionTo(EventStatus::Published);

        $this->assertSame(EventStatus::Published, $event->status);
    }

    public function test_GIVEN_a_pending_review_event_WHEN_rejected_THEN_it_returns_to_draft_with_feedback(): void
    {
        $event = $this->makeEvent()
            ->transitionTo(EventStatus::PendingReview)
            ->transitionTo(EventStatus::Draft, 'Faltam informações sobre o local.');

        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertSame('Faltam informações sobre o local.', $event->rejectionFeedback);
    }

    public function test_GIVEN_a_published_event_WHEN_cancelling_THEN_it_transitions_to_cancelled(): void
    {
        $event = $this->makeEvent()
            ->transitionTo(EventStatus::PendingReview)
            ->transitionTo(EventStatus::Published)
            ->transitionTo(EventStatus::Cancelled);

        $this->assertSame(EventStatus::Cancelled, $event->status);
    }

    public function test_GIVEN_a_published_event_WHEN_its_start_time_passes_THEN_it_transitions_to_ended(): void
    {
        $event = $this->makeEvent()
            ->transitionTo(EventStatus::PendingReview)
            ->transitionTo(EventStatus::Published)
            ->transitionTo(EventStatus::Ended);

        $this->assertSame(EventStatus::Ended, $event->status);
    }

    public function test_GIVEN_a_draft_event_WHEN_publishing_directly_THEN_it_throws(): void
    {
        $this->expectException(DomainException::class);

        $this->makeEvent()->transitionTo(EventStatus::Published);
    }

    public function test_GIVEN_a_cancelled_event_WHEN_transitioning_again_THEN_it_throws(): void
    {
        $event = $this->makeEvent()
            ->transitionTo(EventStatus::PendingReview)
            ->transitionTo(EventStatus::Published)
            ->transitionTo(EventStatus::Cancelled);

        $this->expectException(DomainException::class);

        $event->transitionTo(EventStatus::Draft);
    }
}

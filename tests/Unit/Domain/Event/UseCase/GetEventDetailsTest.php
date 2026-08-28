<?php

namespace Tests\Unit\Domain\Event\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\GetEventDetails;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\Enum\City;

class GetEventDetailsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeEvent(EventStatus $status): Event
    {
        return new Event(
            id: 1,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 10,
            title: 'Noite do Rock',
            description: 'Show ao vivo.',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            status: $status,
        );
    }

    public function test_GIVEN_a_published_event_WHEN_getting_details_THEN_it_returns_the_event_with_tagged_promoters(): void
    {
        $event = $this->makeEvent(EventStatus::Published);
        $promoter = new Promoter(
            id: 5,
            userId: 1,
            name: 'DJ Promo',
            contactPhone: '27999999999',
            contactEmail: 'promo@example.com',
        );

        $events = Mockery::mock(EventRepository::class);
        $events->shouldReceive('findById')->once()->with(1)->andReturn($event);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findTaggedForEvent')->once()->with(1)->andReturn([$promoter]);

        $useCase = new GetEventDetails($events, $promoters);
        $detail = $useCase->execute(1);

        $this->assertNotNull($detail);
        $this->assertSame($event, $detail->event);
        $this->assertSame([$promoter], $detail->taggedPromoters);
    }

    public function test_GIVEN_a_cancelled_event_WHEN_getting_details_THEN_it_is_still_returned_not_null(): void
    {
        $event = $this->makeEvent(EventStatus::Cancelled);

        $events = Mockery::mock(EventRepository::class);
        $events->shouldReceive('findById')->once()->with(1)->andReturn($event);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findTaggedForEvent')->once()->with(1)->andReturn([]);

        $detail = (new GetEventDetails($events, $promoters))->execute(1);

        $this->assertNotNull($detail);
        $this->assertSame(EventStatus::Cancelled, $detail->event->status);
    }

    public function test_GIVEN_an_ended_event_WHEN_getting_details_THEN_it_is_still_returned_not_null(): void
    {
        $event = $this->makeEvent(EventStatus::Ended);

        $events = Mockery::mock(EventRepository::class);
        $events->shouldReceive('findById')->once()->with(1)->andReturn($event);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findTaggedForEvent')->once()->with(1)->andReturn([]);

        $detail = (new GetEventDetails($events, $promoters))->execute(1);

        $this->assertNotNull($detail);
        $this->assertSame(EventStatus::Ended, $detail->event->status);
    }

    public function test_GIVEN_a_nonexistent_event_id_WHEN_getting_details_THEN_it_returns_null(): void
    {
        $events = Mockery::mock(EventRepository::class);
        $events->shouldReceive('findById')->once()->with(999)->andReturn(null);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldNotReceive('findTaggedForEvent');

        $detail = (new GetEventDetails($events, $promoters))->execute(999);

        $this->assertNull($detail);
    }

    public function test_GIVEN_an_event_with_no_tagged_promoters_WHEN_getting_details_THEN_the_list_is_empty(): void
    {
        $event = $this->makeEvent(EventStatus::Published);

        $events = Mockery::mock(EventRepository::class);
        $events->shouldReceive('findById')->once()->with(1)->andReturn($event);

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findTaggedForEvent')->once()->with(1)->andReturn([]);

        $detail = (new GetEventDetails($events, $promoters))->execute(1);

        $this->assertSame([], $detail->taggedPromoters);
    }
}

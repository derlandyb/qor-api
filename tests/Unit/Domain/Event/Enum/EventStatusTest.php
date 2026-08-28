<?php

namespace Tests\Unit\Domain\Event\Enum;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventStatus;
use ValueError;

class EventStatusTest extends TestCase
{
    public function test_GIVEN_a_valid_raw_value_WHEN_cast_THEN_it_resolves_to_the_correct_case(): void
    {
        $this->assertSame(EventStatus::Published, EventStatus::from('published'));
        $this->assertSame(EventStatus::Draft, EventStatus::from('draft'));
        $this->assertSame(EventStatus::PendingReview, EventStatus::from('pending_review'));
        $this->assertSame(EventStatus::Cancelled, EventStatus::from('cancelled'));
        $this->assertSame(EventStatus::Encerrado, EventStatus::from('encerrado'));
    }

    public function test_GIVEN_an_invalid_raw_value_WHEN_cast_THEN_it_throws(): void
    {
        $this->expectException(ValueError::class);

        EventStatus::from('not_a_status');
    }
}

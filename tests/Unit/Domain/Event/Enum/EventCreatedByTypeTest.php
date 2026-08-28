<?php

namespace Tests\Unit\Domain\Event\Enum;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use ValueError;

class EventCreatedByTypeTest extends TestCase
{
    public function test_GIVEN_a_valid_raw_value_WHEN_cast_THEN_it_resolves_to_the_correct_case(): void
    {
        $this->assertSame(EventCreatedByType::VenueAdmin, EventCreatedByType::from('venue_admin'));
        $this->assertSame(EventCreatedByType::Promoter, EventCreatedByType::from('promoter'));
    }

    public function test_GIVEN_an_invalid_raw_value_WHEN_cast_THEN_it_throws(): void
    {
        $this->expectException(ValueError::class);

        EventCreatedByType::from('not_a_type');
    }
}

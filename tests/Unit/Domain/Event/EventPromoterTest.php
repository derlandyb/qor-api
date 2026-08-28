<?php

namespace Tests\Unit\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\EventPromoter;

class EventPromoterTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_entity_holds_the_tag(): void
    {
        $taggedAt = new DateTimeImmutable('now');

        $tag = new EventPromoter(eventId: 1, promoterId: 2, taggedAt: $taggedAt);

        $this->assertSame(1, $tag->eventId);
        $this->assertSame(2, $tag->promoterId);
        $this->assertSame($taggedAt, $tag->taggedAt);
    }
}

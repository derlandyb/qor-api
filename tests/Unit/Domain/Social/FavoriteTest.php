<?php

namespace Tests\Unit\Domain\Social;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\Favorite;

final class FavoriteTest extends TestCase
{
    public function test_GIVEN_valid_ids_WHEN_creating_a_favorite_THEN_it_is_constructed_successfully(): void
    {
        $favorite = new Favorite(id: null, userId: 1, eventId: 2);

        $this->assertSame(1, $favorite->userId);
        $this->assertSame(2, $favorite->eventId);
    }

    public function test_GIVEN_a_non_positive_user_id_WHEN_creating_a_favorite_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Favorite(id: null, userId: 0, eventId: 2);
    }

    public function test_GIVEN_a_non_positive_event_id_WHEN_creating_a_favorite_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Favorite(id: null, userId: 1, eventId: 0);
    }
}

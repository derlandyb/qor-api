<?php

namespace Tests\Unit\Domain\Social\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\Social\UseCase\RemoveFriend;

final class RemoveFriendTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_two_friends_WHEN_removing_the_friendship_THEN_it_delegates_to_the_repository(): void
    {
        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('remove')->once()->with(1, 2);

        $useCase = new RemoveFriend($repository);

        $useCase->execute(1, 2);
    }

    public function test_GIVEN_a_removal_request_WHEN_executed_from_either_side_THEN_the_repository_receives_the_correct_ids(): void
    {
        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('remove')->once()->with(2, 1);

        $useCase = new RemoveFriend($repository);

        $useCase->execute(2, 1);
    }
}

<?php

namespace Tests\Unit\Domain\Notification\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Notification\UseCase\UpdateNotificationPreference;

final class UpdateNotificationPreferenceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_a_fan_WHEN_updating_their_preferences_THEN_they_are_persisted_via_the_repository(): void
    {
        $preference = new NotificationPreference(id: null, userId: 1, pushEnabled: false, silenceAll: false);

        $repository = Mockery::mock(NotificationPreferenceRepository::class);
        $repository->shouldReceive('save')->once()->with($preference)->andReturn($preference);

        $useCase = new UpdateNotificationPreference($repository);

        $this->assertSame($preference, $useCase->execute($preference));
    }

    public function test_GIVEN_global_silence_is_enabled_WHEN_updating_THEN_it_is_persisted_as_is(): void
    {
        $preference = new NotificationPreference(id: 3, userId: 1, silenceAll: true);

        $repository = Mockery::mock(NotificationPreferenceRepository::class);
        $repository->shouldReceive('save')->once()->with($preference)->andReturn($preference);

        $useCase = new UpdateNotificationPreference($repository);

        $result = $useCase->execute($preference);

        $this->assertTrue($result->silenceAll);
    }

    public function test_GIVEN_per_trigger_toggles_WHEN_updating_THEN_they_are_forwarded_unmodified(): void
    {
        $preference = new NotificationPreference(
            id: 3,
            userId: 1,
            triggerNearbyReminder: false,
            triggerFriendInterest: false,
            triggerNewRegional: false,
        );

        $repository = Mockery::mock(NotificationPreferenceRepository::class);
        $repository->shouldReceive('save')->once()->with($preference)->andReturn($preference);

        $useCase = new UpdateNotificationPreference($repository);

        $result = $useCase->execute($preference);

        $this->assertFalse($result->triggerNearbyReminder);
        $this->assertFalse($result->triggerFriendInterest);
        $this->assertFalse($result->triggerNewRegional);
    }

    public function test_GIVEN_a_saved_preference_WHEN_the_next_dispatch_reads_it_THEN_it_reflects_the_update_immediately(): void
    {
        $preference = new NotificationPreference(id: 3, userId: 1, emailEnabled: false);

        $repository = Mockery::mock(NotificationPreferenceRepository::class);
        $repository->shouldReceive('save')->once()->with($preference)->andReturn($preference);
        $repository->shouldReceive('findByUserId')->once()->with(1)->andReturn($preference);

        $useCase = new UpdateNotificationPreference($repository);
        $useCase->execute($preference);

        $this->assertFalse($repository->findByUserId(1)->emailEnabled);
    }
}

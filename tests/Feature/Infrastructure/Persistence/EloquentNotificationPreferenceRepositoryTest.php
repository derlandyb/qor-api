<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentNotificationPreferenceRepository;
use Tests\TestCase;

class EloquentNotificationPreferenceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_no_preference_row_WHEN_finding_by_user_id_THEN_it_returns_null(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentNotificationPreferenceRepository();

        $this->assertNull($repository->findByUserId($user->id));
    }

    public function test_GIVEN_a_new_preference_WHEN_saving_THEN_it_is_persisted_and_findable(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentNotificationPreferenceRepository();

        $preference = new NotificationPreference(id: null, userId: $user->id, silenceAll: true);
        $repository->save($preference);

        $found = $repository->findByUserId($user->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->silenceAll);
    }

    public function test_GIVEN_an_existing_preference_WHEN_saving_an_updated_version_THEN_it_updates_the_same_row_rather_than_creating_a_new_one(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentNotificationPreferenceRepository();

        $repository->save(new NotificationPreference(id: null, userId: $user->id, silenceAll: false));
        $repository->save(new NotificationPreference(id: null, userId: $user->id, silenceAll: true));

        $this->assertDatabaseCount('notification_preferences', 1);
        $this->assertTrue($repository->findByUserId($user->id)->silenceAll);
    }
}

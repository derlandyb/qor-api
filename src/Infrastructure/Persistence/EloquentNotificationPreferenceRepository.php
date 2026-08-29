<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\NotificationPreferenceModel;

class EloquentNotificationPreferenceRepository implements NotificationPreferenceRepository
{
    public function findByUserId(int $userId): ?NotificationPreference
    {
        $model = NotificationPreferenceModel::where('user_id', $userId)->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function save(NotificationPreference $preference): NotificationPreference
    {
        $model = NotificationPreferenceModel::updateOrCreate(
            ['user_id' => $preference->userId],
            [
                'push_enabled' => $preference->pushEnabled,
                'email_enabled' => $preference->emailEnabled,
                'silence_all' => $preference->silenceAll,
                'trigger_nearby_reminder' => $preference->triggerNearbyReminder,
                'trigger_event_changed_cancelled' => $preference->triggerEventChangedCancelled,
                'trigger_friend_interest' => $preference->triggerFriendInterest,
                'trigger_new_regional' => $preference->triggerNewRegional,
            ],
        );

        return $this->toDomain($model);
    }

    private function toDomain(NotificationPreferenceModel $model): NotificationPreference
    {
        return new NotificationPreference(
            id: $model->id,
            userId: $model->user_id,
            pushEnabled: $model->push_enabled,
            emailEnabled: $model->email_enabled,
            silenceAll: $model->silence_all,
            triggerNearbyReminder: $model->trigger_nearby_reminder,
            triggerEventChangedCancelled: $model->trigger_event_changed_cancelled,
            triggerFriendInterest: $model->trigger_friend_interest,
            triggerNewRegional: $model->trigger_new_regional,
        );
    }
}

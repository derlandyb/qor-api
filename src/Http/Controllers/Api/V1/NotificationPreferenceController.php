<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Notification\UseCase\UpdateNotificationPreference;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\V1\UpdateNotificationPreferenceRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly UpdateNotificationPreference $updateNotificationPreference,
        private readonly NotificationPreferenceRepository $preferences,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $preference = $this->preferences->findByUserId((int) $model->id)
            ?? new NotificationPreference(id: null, userId: (int) $model->id);

        return response()->json(['data' => $this->preferenceToArray($preference)]);
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $existing = $this->preferences->findByUserId((int) $model->id)
            ?? new NotificationPreference(id: null, userId: (int) $model->id);

        $preference = new NotificationPreference(
            id: $existing->id,
            userId: (int) $model->id,
            pushEnabled: (bool) $request->boolean('push_enabled', $existing->pushEnabled),
            emailEnabled: (bool) $request->boolean('email_enabled', $existing->emailEnabled),
            silenceAll: (bool) $request->boolean('silence_all', $existing->silenceAll),
            triggerNearbyReminder: (bool) $request->boolean('trigger_nearby_reminder', $existing->triggerNearbyReminder),
            triggerEventChangedCancelled: (bool) $request->boolean('trigger_event_changed_cancelled', $existing->triggerEventChangedCancelled),
            triggerFriendInterest: (bool) $request->boolean('trigger_friend_interest', $existing->triggerFriendInterest),
            triggerNewRegional: (bool) $request->boolean('trigger_new_regional', $existing->triggerNewRegional),
        );

        $saved = $this->updateNotificationPreference->execute($preference);

        return response()->json(['data' => $this->preferenceToArray($saved)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function preferenceToArray(NotificationPreference $preference): array
    {
        return [
            'push_enabled' => $preference->pushEnabled,
            'email_enabled' => $preference->emailEnabled,
            'silence_all' => $preference->silenceAll,
            'trigger_nearby_reminder' => $preference->triggerNearbyReminder,
            'trigger_event_changed_cancelled' => $preference->triggerEventChangedCancelled,
            'trigger_friend_interest' => $preference->triggerFriendInterest,
            'trigger_new_regional' => $preference->triggerNewRegional,
        ];
    }
}

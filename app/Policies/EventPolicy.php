<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Ownership authorization on top of the `manage-events` gate's broader "are you verified
 * at all" check — this answers "which events," not "can you manage events at all."
 */
final class EventPolicy
{
    public function manage(User $user, Event $event): bool
    {
        return $event->promoters->contains('id', $user->promoterProfile?->id)
            || $event->venue_id === $user->venueProfile?->id;
    }
}

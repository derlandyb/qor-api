<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Ownership authorization on top of the `manage-events` gate's broader "are you verified
 * at all" check — this answers "which events," not "can you manage events at all."
 */
final class EventPolicy
{
    public function manage(User $user, Event $event): Response
    {
        $owns = $event->promoters->contains('id', $user->promoterProfile?->id)
            || $event->venue_id === $user->venueProfile?->id;

        // pt-BR denial message — otherwise the default AuthorizationException text
        // ("This action is unauthorized.") leaks in English to a JSON API client.
        return $owns
            ? Response::allow()
            : Response::deny('Você não tem permissão para executar esta ação.');
    }
}

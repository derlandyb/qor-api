<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Lets public, anonymous-accessible endpoints (feed/detail/map) additively annotate
     * `isFavorited` for a request that happens to carry a valid Sanctum token, without
     * requiring those routes to sit behind `auth:sanctum`.
     */
    protected function attachFavoritedEventIds(Request $request): void
    {
        $user = $request->user('sanctum');

        // Left unset (not set to an empty collection) for anonymous requests, so
        // EventResource/EventDetailResource's when() omits `isFavorited` entirely —
        // matching event-feed's "absent, not null/false" convention for optional fields.
        if ($user !== null) {
            $request->attributes->set('favoritedEventIds', $user->favoriteEvents()->pluck('events.id'));
        }
    }
}

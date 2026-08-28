<?php

namespace QOR\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum's token guard resolves any valid token's tokenable model
 * regardless of which named guard/provider is configured (it only
 * segregates by provider for stateful/cookie requests, not bearer
 * tokens) — this middleware closes that gap for the fan guard so an
 * admin's bearer token cannot authenticate against /api/v1 routes.
 */
class EnsureFanIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('fan')->user() instanceof UserModel) {
            abort(401, 'Não autenticado.');
        }

        return $next($request);
    }
}

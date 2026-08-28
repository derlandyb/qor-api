<?php

namespace QOR\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QOR\App\Models\AdminUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors EnsureFanIdentity for the admin guard — see that class for
 * why this check is necessary on top of the `auth:admin` middleware.
 */
class EnsureAdminIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->user() instanceof AdminUser) {
            abort(401, 'Não autenticado.');
        }

        return $next($request);
    }
}

<?php

namespace QOR\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADMIN-07–ADMIN-19: the account- and event-approval queues are restricted
 * to Super Admins — an approved Venue/Promoter admin is a valid `admin`
 * guard identity but must still be denied here. Applied after `guard.admin`
 * on approval-queue routes.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var AdminUserModel $user */
        $user = Auth::guard('admin')->user();

        if (! $user->is_super_admin) {
            abort(403, 'Acesso restrito ao Super Admin.');
        }

        return $next($request);
    }
}

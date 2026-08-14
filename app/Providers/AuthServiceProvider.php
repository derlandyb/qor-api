<?php

namespace App\Providers;

use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Event::class => EventPolicy::class,
    ];

    // The pt-BR message every Gate below denies with — otherwise a raw AuthorizationException's
    // default "This action is unauthorized." leaks verbatim to a JSON API client.
    private const DENIAL_MESSAGE = 'Você não tem permissão para executar esta ação.';

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // The reuse surface `event-publishing` applies as `can:manage-events` route middleware.
        // Only a Verified promoter/venue profile unlocks it — standard accounts, and anyone still
        // unverified/pending, are denied.
        Gate::define('manage-events', fn (User $user) => ($user->promoterProfile ?? $user->venueProfile)?->verification_status === VerificationStatus::Verified
            ? Response::allow()
            : Response::deny(self::DENIAL_MESSAGE));

        // moderation's retroactive definition of "admin" — gates verification's admin-only
        // routes. Deepened by admin-auth into two tiers: Admin and SuperAdmin both count here.
        Gate::define('admin', fn (User $user) => $user->isAdmin()
            ? Response::allow()
            : Response::deny(self::DENIAL_MESSAGE));

        // admin-auth: the higher tier — staff-account management and verification revocation,
        // the one existing action judged high-impact enough to restrict beyond flat `admin`.
        Gate::define('super-admin', fn (User $user) => $user->role === Role::SuperAdmin
            ? Response::allow()
            : Response::deny(self::DENIAL_MESSAGE));
    }
}

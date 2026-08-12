<?php

namespace App\Providers;

use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
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

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // The reuse surface `event-publishing` applies as `can:manage-events` route middleware.
        // Only a Verified promoter/venue profile unlocks it — standard accounts, and anyone still
        // unverified/pending, are denied.
        Gate::define('manage-events', fn (User $user) => ($user->promoterProfile ?? $user->venueProfile)?->verification_status === VerificationStatus::Verified);
    }
}

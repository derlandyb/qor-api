<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The reset-password notification's default link builds a `password.reset`-named URL,
        // which resolves to this API's own APP_URL — a browser GET against it 405s (that route
        // only accepts POST). Point it at the frontend's actual reset-password page instead.
        ResetPassword::createUrlUsing(function (User $notifiable, string $token) {
            return sprintf(
                '%s/reset-password?token=%s&email=%s',
                rtrim((string) config('app.frontend_url'), '/'),
                $token,
                urlencode($notifiable->getEmailForPasswordReset()),
            );
        });
    }
}

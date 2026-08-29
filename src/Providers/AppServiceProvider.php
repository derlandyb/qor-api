<?php

namespace QOR\App\Providers;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Notification\NotificationLogRepository;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\User\PasswordResetPort;
use QOR\App\Domain\User\UserRepository;
use QOR\App\Domain\Venue\VenueRepository;
use QOR\App\Infrastructure\Auth\LaravelEmailVerificationAdapter;
use QOR\App\Infrastructure\Auth\LaravelPasswordResetAdapter;
use QOR\App\Infrastructure\Notification\FcmPushSender;
use QOR\App\Infrastructure\Notification\SesEmailSender;
use QOR\App\Infrastructure\Persistence\EloquentAdminAccountRepository;
use QOR\App\Infrastructure\Persistence\EloquentApprovalDecisionRepository;
use QOR\App\Infrastructure\Persistence\EloquentConsentRepository;
use QOR\App\Infrastructure\Persistence\EloquentEventRepository;
use QOR\App\Infrastructure\Persistence\EloquentFavoriteRepository;
use QOR\App\Infrastructure\Persistence\EloquentFriendshipRepository;
use QOR\App\Infrastructure\Persistence\EloquentNotificationLogRepository;
use QOR\App\Infrastructure\Persistence\EloquentNotificationPreferenceRepository;
use QOR\App\Infrastructure\Persistence\EloquentPromoterRepository;
use QOR\App\Infrastructure\Persistence\EloquentUserRepository;
use QOR\App\Infrastructure\Persistence\EloquentVenueRepository;
use QOR\App\Infrastructure\Security\LaravelPasswordHasher;
use QOR\App\Infrastructure\Storage\S3UploadAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EventRepository::class, EloquentEventRepository::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(VenueRepository::class, EloquentVenueRepository::class);
        $this->app->bind(PromoterRepository::class, EloquentPromoterRepository::class);
        $this->app->bind(ApprovalDecisionRepository::class, EloquentApprovalDecisionRepository::class);
        $this->app->bind(AdminAccountRepository::class, EloquentAdminAccountRepository::class);
        $this->app->bind(ConsentRepository::class, EloquentConsentRepository::class);
        $this->app->bind(FavoriteRepository::class, EloquentFavoriteRepository::class);
        $this->app->bind(FriendshipRepository::class, EloquentFriendshipRepository::class);
        $this->app->bind(NotificationPreferenceRepository::class, EloquentNotificationPreferenceRepository::class);
        $this->app->bind(NotificationLogRepository::class, EloquentNotificationLogRepository::class);
        $this->app->bind(FileUploadPort::class, S3UploadAdapter::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(EmailVerificationPort::class, LaravelEmailVerificationAdapter::class);
        $this->app->bind(PasswordResetPort::class, LaravelPasswordResetAdapter::class);

        $this->app->singleton(PasswordPolicy::class, function () {
            /** @var int $min */
            $min = config('qor.auth.password_rules.min');
            /** @var bool $mixedCase */
            $mixedCase = config('qor.auth.password_rules.mixed_case');
            /** @var bool $numbers */
            $numbers = config('qor.auth.password_rules.numbers');

            return new PasswordPolicy($min, $mixedCase, $numbers);
        });

        // NotificationDispatcher (ARCHITECTURE.md §6.1) takes the push and
        // email senders as two positional NotificationSender ports — bound
        // here explicitly rather than via NotificationSender::class, which
        // would be ambiguous between FcmPushSender and SesEmailSender.
        $this->app->bind(NotificationDispatcher::class, function ($app) {
            /** @var int $consolidationWindowMinutes */
            $consolidationWindowMinutes = config('qor.notifications.consolidation_window_minutes');

            return new NotificationDispatcher(
                $app->make(NotificationPreferenceRepository::class),
                $app->make(NotificationLogRepository::class),
                $app->make(FcmPushSender::class),
                $app->make(SesEmailSender::class),
                $consolidationWindowMinutes,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Model factory discovery assumes the default App\Models\ root
        // namespace; this repo's models live under QOR\App\Models\ instead.
        Factory::guessFactoryNamesUsing(
            /**
             * @param  class-string<Model>  $modelName
             * @return class-string<Factory<Model>>
             */
            function (string $modelName): string {
                /** @var class-string<Factory<Model>> */
                return 'Database\\Factories\\'.Str::afterLast($modelName, '\\').'Factory';
            }
        );

        RateLimiter::for('qor-public-api', function (Request $request) {
            /** @var int $limit */
            $limit = config('qor.rate_limits.public_api');

            return Limit::perMinute($limit)->by($request->ip());
        });

        RateLimiter::for('qor-auth', function (Request $request) {
            /** @var int $limit */
            $limit = config('qor.rate_limits.auth');

            return Limit::perMinute($limit)->by($request->ip());
        });

        // The reset link opens a client-app screen (mobile deep link / web
        // route — not built yet, no submodule feature work has started per
        // STATE.md) that then POSTs to /api/v1/auth/password/reset with the
        // token; there's no server-rendered "password.reset" page here.
        ResetPasswordNotification::createUrlUsing(function (mixed $notifiable, string $token): string {
            /** @var CanResetPassword $notifiable */
            /** @var string $appUrl */
            $appUrl = config('app.url');

            return $appUrl.'/redefinir-senha?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}

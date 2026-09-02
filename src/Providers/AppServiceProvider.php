<?php

namespace QOR\App\Providers;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\SubscriptionRepository;
use QOR\App\Domain\Event\DomainEvent\EventCancelled;
use QOR\App\Domain\Event\DomainEvent\EventChanged;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Notification\NotificationLogRepository;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Notification\UseCase\DetectNearbyReminders;
use QOR\App\Domain\Notification\UseCase\DetectRegionalPublishes;
use QOR\App\Domain\Notification\UseCase\HandleEventChangedOrCancelled;
use QOR\App\Domain\Notification\UseCase\HandleFriendInterest;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\DomainEventPublisher;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\Shared\TransactionManager;
use QOR\App\Domain\Social\DomainEvent\FavoriteCreated;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Domain\User\OtpVerificationPort;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\User\PasswordResetPort;
use QOR\App\Domain\User\UserAddressRepository;
use QOR\App\Domain\User\UserFavoriteGenreRepository;
use QOR\App\Domain\User\UserRepository;
use QOR\App\Domain\Venue\VenueRepository;
use QOR\App\Infrastructure\Auth\LaravelEmailVerificationAdapter;
use QOR\App\Infrastructure\Auth\LaravelPasswordResetAdapter;
use QOR\App\Infrastructure\Auth\OtpAdapter;
use QOR\App\Infrastructure\Events\LaravelDomainEventPublisher;
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
use QOR\App\Infrastructure\Persistence\EloquentPlanRepository;
use QOR\App\Infrastructure\Persistence\EloquentPromoterRepository;
use QOR\App\Infrastructure\Persistence\EloquentSubscriptionRepository;
use QOR\App\Infrastructure\Persistence\EloquentUserAddressRepository;
use QOR\App\Infrastructure\Persistence\EloquentUserFavoriteGenreRepository;
use QOR\App\Infrastructure\Persistence\EloquentUserRepository;
use QOR\App\Infrastructure\Persistence\EloquentVenueRepository;
use QOR\App\Infrastructure\Persistence\LaravelTransactionManager;
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
        $this->app->bind(PlanRepository::class, EloquentPlanRepository::class);
        $this->app->bind(SubscriptionRepository::class, EloquentSubscriptionRepository::class);
        $this->app->bind(ConsentRepository::class, EloquentConsentRepository::class);
        $this->app->bind(FavoriteRepository::class, EloquentFavoriteRepository::class);
        $this->app->bind(FriendshipRepository::class, EloquentFriendshipRepository::class);
        $this->app->bind(NotificationPreferenceRepository::class, EloquentNotificationPreferenceRepository::class);
        $this->app->bind(NotificationLogRepository::class, EloquentNotificationLogRepository::class);
        $this->app->bind(UserAddressRepository::class, EloquentUserAddressRepository::class);
        $this->app->bind(UserFavoriteGenreRepository::class, EloquentUserFavoriteGenreRepository::class);
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);
        $this->app->bind(FileUploadPort::class, S3UploadAdapter::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(EmailVerificationPort::class, LaravelEmailVerificationAdapter::class);
        $this->app->bind(PasswordResetPort::class, LaravelPasswordResetAdapter::class);
        $this->app->bind(OtpVerificationPort::class, OtpAdapter::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);

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

        // Scheduled detectors (T85, routes/console.php) — leadHours/
        // batchWindowMinutes default to the same values as the use cases'
        // own constructor defaults, but the domain layer can't call
        // config() itself (§8.5), so the real values are wired here.
        $this->app->bind(DetectNearbyReminders::class, function ($app) {
            /** @var int $leadHours */
            $leadHours = config('qor.notifications.nearby_reminder_lead_hours');

            return new DetectNearbyReminders(
                $app->make(UserAddressRepository::class),
                $app->make(FavoriteRepository::class),
                $app->make(NotificationDispatcher::class),
                $leadHours,
            );
        });

        $this->app->bind(DetectRegionalPublishes::class, function ($app) {
            /** @var int $batchWindowMinutes */
            $batchWindowMinutes = config('qor.notifications.regional_batch_window_minutes');

            return new DetectRegionalPublishes(
                $app->make(UserAddressRepository::class),
                $app->make(EventRepository::class),
                $app->make(NotificationDispatcher::class),
                $batchWindowMinutes,
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

        // Separate from qor-auth (IP-keyed): an OTP attack targets one
        // victim's *identifier* from many IPs, which an IP-keyed limiter
        // alone can't bound — this closes that gap for the 4 OTP-issuing/
        // verifying endpoints (see OtpAdapter's per-code attempt lockout,
        // which this limiter backs up at the request layer).
        RateLimiter::for('qor-otp', function (Request $request) {
            /** @var int $limit */
            $limit = config('qor.rate_limits.otp');
            /** @var string $email */
            $email = $request->input('email', '');

            return Limit::perMinute($limit)->by('otp:'.$email);
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

        // Domain-event wiring (T86/T87, notifications/design.md's
        // Integration Points table): EditEvent/CancelEvent/
        // DecideEventApproval and ToggleFavorite publish plain domain
        // events through DomainEventPublisher (framework-agnostic from the
        // domain layer's side); this composition-root registration is what
        // actually connects them to the event-driven handlers built in
        // Phase 5b.
        Event::listen(EventChanged::class, function (EventChanged $event): void {
            $this->app->make(HandleEventChangedOrCancelled::class)
                ->handle($event->eventId, NotificationTriggerType::EventChanged);
        });

        Event::listen(EventCancelled::class, function (EventCancelled $event): void {
            $this->app->make(HandleEventChangedOrCancelled::class)
                ->handle($event->eventId, NotificationTriggerType::EventCancelled);
        });

        Event::listen(FavoriteCreated::class, function (FavoriteCreated $event): void {
            $this->app->make(HandleFriendInterest::class)->handle($event->userId, $event->eventId);
        });
    }
}

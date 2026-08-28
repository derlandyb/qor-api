<?php

namespace QOR\App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\User\UserRepository;
use QOR\App\Domain\Venue\VenueRepository;
use QOR\App\Infrastructure\Persistence\EloquentApprovalDecisionRepository;
use QOR\App\Infrastructure\Persistence\EloquentEventRepository;
use QOR\App\Infrastructure\Persistence\EloquentPromoterRepository;
use QOR\App\Infrastructure\Persistence\EloquentUserRepository;
use QOR\App\Infrastructure\Persistence\EloquentVenueRepository;
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
        $this->app->bind(FileUploadPort::class, S3UploadAdapter::class);
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
    }
}

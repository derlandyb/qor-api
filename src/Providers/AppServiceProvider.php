<?php

namespace QOR\App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

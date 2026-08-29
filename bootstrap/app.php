<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use QOR\App\Domain\Billing\Exception\QuotaExceeded;
use QOR\App\Http\Middleware\EnsureAdminIdentity;
use QOR\App\Http\Middleware\EnsureFanIdentity;
use QOR\App\Http\Middleware\EnsureSuperAdmin;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));

            Route::middleware('api')
                ->prefix('api/admin/v1')
                ->group(base_path('routes/api_admin_v1.php'));
        },
    )
    // app/ was renamed to src/ (see the useAppPath note below), so the default
    // withCommands() discovery path (app_path('Console/Commands'), resolved
    // before useAppPath() runs) must be given explicitly here.
    ->withCommands([__DIR__.'/../src/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'guard.fan' => EnsureFanIdentity::class,
            'guard.admin' => EnsureAdminIdentity::class,
            'guard.super-admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Recurso não encontrado.'], 404);
            }
        });

        // QuotaExceeded is an InvalidArgumentException, so its render
        // callback must be registered before the generic one below —
        // Handler::renderViaCallbacks() returns the first matching
        // callback's response, in registration order.
        $exceptions->render(function (QuotaExceeded $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'quota_exceeded'], 422);
            }
        });

        // Domain-layer business-rule violations (duplicate email, weak
        // password, expired link, ...) surface as a generic 422 with the
        // use case's own pt-BR message. Controllers catch and remap the few
        // exceptions that need a different status (e.g. invalid credentials).
        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        // Illegal event-state-machine transitions (Event::transitionTo) —
        // same treatment as InvalidArgumentException above.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
    })->create();

// app/ was renamed to src/ (ARCHITECTURE.md §8.6) — without this,
// Illuminate\Foundation\Application::getNamespace() can't match src/ against
// composer.json's QOR\App\ PSR-4 mapping (it only checks the default
// app_path()) and throws "Unable to detect application namespace" the first
// time anything needs it (e.g. Mail Markdown component resolution).
$app->useAppPath($app->basePath('src'));

return $app;

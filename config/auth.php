<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'fan'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'fans'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        // Fan credential space — mobile bearer tokens, website/landing SPA
        // cookie sessions. Never shares a provider with the admin guard
        // (ARCHITECTURE §2 — two guards, not one shared table).
        'fan' => [
            'driver' => 'sanctum',
            'provider' => 'fans',
        ],

        // Venue Admin / Promoter / Super Admin credential space — admin
        // panel SPA cookie sessions only, under /api/admin/v1.
        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],

        // Real session-backed guards Sanctum's stateful SPA check actually
        // authenticates against (config/sanctum.php's 'guard' list) — the
        // 'fan'/'admin' guards above are per-request Sanctum guards that,
        // for a stateful (cookie) request, delegate to whichever of these
        // has a logged-in session; a login endpoint calls these directly
        // (e.g. AdminAuthController::login() -> Auth::guard('admin-session')
        // ->login($model)). Without a project-specific pair here, Sanctum
        // silently falls back to its own generic 'web'/'users' defaults,
        // which point at the stock (nonexistent in this app) App\Models\User.
        'fan-session' => [
            'driver' => 'session',
            'provider' => 'fans',
        ],
        'admin-session' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'fans' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_FAN_MODEL', \QOR\App\Infrastructure\Persistence\Eloquent\UserModel::class),
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_ADMIN_MODEL', \QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'fans' => [
            'provider' => 'fans',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            // Kept in lockstep with qor.auth.password_reset_ttl_minutes via the
            // same env var — config/qor.php isn't guaranteed to be loaded yet
            // when this file is evaluated, so this reads the env var directly.
            'expire' => (int) env('QOR_PASSWORD_RESET_TTL_MINUTES', 60),
            'throttle' => 60,
        ],

        'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => (int) env('QOR_PASSWORD_RESET_TTL_MINUTES', 60),
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'public_page_size' => (int) env('QOR_PUBLIC_PAGE_SIZE', 20),
        'admin_page_size' => (int) env('QOR_ADMIN_PAGE_SIZE', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event discovery
    |--------------------------------------------------------------------------
    */
    'polling' => [
        'event_list_interval_seconds' => (int) env('QOR_EVENT_LIST_POLLING_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'password_rules' => [
            'min' => (int) env('QOR_PASSWORD_MIN_LENGTH', 8),
            'mixed_case' => true,
            'numbers' => true,
        ],
        'password_reset_ttl_minutes' => (int) env('QOR_PASSWORD_RESET_TTL_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'auth' => (int) env('QOR_RATE_LIMIT_AUTH', 5),
        'public_api' => (int) env('QOR_RATE_LIMIT_PUBLIC_API', 60),
    ],

];

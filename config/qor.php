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

    /*
    |--------------------------------------------------------------------------
    | Media uploads (ARCHITECTURE.md §10)
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'image' => [
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size_kb' => (int) env('QOR_UPLOAD_IMAGE_MAX_SIZE_KB', 5120),
            'min_width_px' => (int) env('QOR_UPLOAD_IMAGE_MIN_WIDTH_PX', 400),
            'min_height_px' => (int) env('QOR_UPLOAD_IMAGE_MIN_HEIGHT_PX', 400),
            'max_width_px' => (int) env('QOR_UPLOAD_IMAGE_MAX_WIDTH_PX', 6000),
            'max_height_px' => (int) env('QOR_UPLOAD_IMAGE_MAX_HEIGHT_PX', 6000),
        ],
    ],

];

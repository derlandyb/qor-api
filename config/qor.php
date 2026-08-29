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
        'email_verification_ttl_minutes' => (int) env('QOR_EMAIL_VERIFICATION_TTL_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legal / consent (ARCHITECTURE.md §7)
    |--------------------------------------------------------------------------
    */
    'legal' => [
        'policy_version' => env('QOR_LEGAL_POLICY_VERSION', '1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications (ARCHITECTURE.md §6.1, §14.2)
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'nearby_reminder_lead_hours' => (int) env('QOR_NEARBY_REMINDER_LEAD_HOURS', 24),
        'regional_batch_window_minutes' => (int) env('QOR_REGIONAL_BATCH_WINDOW_MINUTES', 60),
        'consolidation_window_minutes' => (int) env('QOR_NOTIFICATIONS_CONSOLIDATION_WINDOW_MINUTES', 60),
        'fcm' => [
            'server_key' => env('QOR_FCM_SERVER_KEY'),
            'endpoint' => env('QOR_FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
        ],
        'ses' => [
            'from_address' => env('QOR_SES_FROM_ADDRESS', 'no-reply@qor.app'),
        ],
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

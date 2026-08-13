<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin bootstrap credentials
    |--------------------------------------------------------------------------
    |
    | Read by SuperAdminSeeder to create the platform's first admin-panel account.
    | Never commit real values — .env.example only carries placeholders.
    |
    */

    'super_admin_email' => env('SUPER_ADMIN_EMAIL'),

    'super_admin_initial_password' => env('SUPER_ADMIN_INITIAL_PASSWORD'),

];

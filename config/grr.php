<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Admin Account
    |--------------------------------------------------------------------------
    |
    | Credentials for the admin account created by AdminUserSeeder. The
    | password is read from the environment and has no default: the seeder
    | skips the account when it is not set. Never commit a real password.
    |
    */

    'admin_seed' => [
        'employee_code' => env('SEED_ADMIN_EMPLOYEE_CODE', 'ADMIN001'),
        'name' => env('SEED_ADMIN_NAME', 'Administrator'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

];

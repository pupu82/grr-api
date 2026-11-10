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

    /*
    |--------------------------------------------------------------------------
    | Real Inspection Timing
    |--------------------------------------------------------------------------
    |
    | Default maximum time an inspector has to answer one sample, in
    | milliseconds. An admin may override it per session while the session is
    | still a draft. The client runs the stopwatch; the server validates the
    | reported duration against the session limit.
    |
    */

    'inspection' => [
        'response_time_limit_ms' => (int) env('GRR_RESPONSE_TIME_LIMIT_MS', 90000),
        'response_time_limit_min_ms' => 5000,
        'response_time_limit_max_ms' => 600000,
    ],
];

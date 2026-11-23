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

    /*
    |--------------------------------------------------------------------------
    | User Import
    |--------------------------------------------------------------------------
    |
    | A preview is held server-side until the admin confirms it. The file is
    | never written to disk: only the parsed rows are kept, and no temporary
    | password is generated until confirmation.
    |
    */

    'imports' => [
        'max_file_size_kb' => (int) env('GRR_IMPORT_MAX_FILE_SIZE_KB', 5120),
        'max_rows' => (int) env('GRR_IMPORT_MAX_ROWS', 1000),
        'preview_ttl_minutes' => (int) env('GRR_IMPORT_PREVIEW_TTL_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Exports
    |--------------------------------------------------------------------------
    |
    | Generated evaluation files are written to this disk under the configured
    | directory. The local disk is the default; a production deployment may use
    | any Laravel filesystem disk that supports downloads.
    |
    */

    'exports' => [
        'disk' => env('GRR_EXPORT_DISK', 'local'),
        'directory' => 'evaluation-exports',
    ],
];

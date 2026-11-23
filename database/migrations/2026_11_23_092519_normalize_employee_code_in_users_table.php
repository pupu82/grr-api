<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize stored codes before enforcing uppercase on PostgreSQL. The
     * existing partial unique index still allows a deleted code to be reused.
     */
    public function up(): void
    {
        $collisions = DB::table('users')
            ->whereNull('deleted_at')
            ->selectRaw('UPPER(employee_code) as normalized_code')
            ->groupByRaw('UPPER(employee_code)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_code');

        if ($collisions->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot normalize employee codes: case-insensitive duplicates exist.'
            );
        }

        DB::table('users')->update([
            'employee_code' => DB::raw('UPPER(employee_code)'),
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_employee_code_uppercase_check CHECK (employee_code = UPPER(employee_code))');
        }
    }

    /**
     * Original casing cannot be reconstructed, so normalized codes are kept.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_employee_code_uppercase_check');
        }
    }
};

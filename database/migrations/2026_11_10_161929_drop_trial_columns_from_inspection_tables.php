<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trials are simulations performed outside the application, so they are
     * never stored. The application records exactly one real-inspection
     * verdict per inspector per sample.
     */
    public function up(): void
    {
        Schema::table('inspection_results', function (Blueprint $table) {
            $table->dropUnique('inspection_results_unique_answer');
            $table->dropColumn('trial_number');
        });

        Schema::table('inspection_results', function (Blueprint $table) {
            $table->unique(['participant_id', 'session_sample_id'], 'inspection_results_unique_answer');
        });

        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->dropColumn('trial_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('trial_count')->default(3);
        });

        Schema::table('inspection_results', function (Blueprint $table) {
            $table->dropUnique('inspection_results_unique_answer');
            $table->unsignedTinyInteger('trial_number')->default(1);
        });

        Schema::table('inspection_results', function (Blueprint $table) {
            $table->unique(['participant_id', 'session_sample_id', 'trial_number'], 'inspection_results_unique_answer');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inspection_sessions ADD CONSTRAINT inspection_sessions_trial_count_check CHECK (trial_count BETWEEN 1 AND 3)');
            DB::statement('ALTER TABLE inspection_results ADD CONSTRAINT inspection_results_trial_number_check CHECK (trial_number BETWEEN 1 AND 3)');
        }
    }
};

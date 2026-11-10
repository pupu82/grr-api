<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support the timed real inspection: one verdict (or one timeout) per
     * inspector per sample, answering in strict sample order with a client
     * stopwatch. Trials remain simulations outside the application.
     */
    public function up(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->unsignedInteger('response_time_limit_ms')->default(90000)->after('session_date');
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::table('session_samples', function (Blueprint $table) {
            $table->unique(['session_id', 'position'], 'session_samples_position_unique');
        });

        Schema::table('session_participants', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::table('inspection_results', function (Blueprint $table) {
            $table->string('outcome', 12)->default('answered')->after('session_sample_id');
            $table->unsignedInteger('response_time_ms')->default(0)->after('verdict');
            $table->timestamp('answered_at')->nullable()->after('response_time_ms');
            $table->string('verdict', 10)->nullable()->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inspection_results ADD CONSTRAINT inspection_results_outcome_check CHECK (outcome IN ('answered', 'timed_out'))");
            DB::statement("ALTER TABLE inspection_results ADD CONSTRAINT inspection_results_outcome_verdict_check CHECK ((outcome = 'answered' AND verdict IS NOT NULL) OR (outcome = 'timed_out' AND verdict IS NULL))");
            DB::statement('ALTER TABLE inspection_results ADD CONSTRAINT inspection_results_response_time_check CHECK (response_time_ms >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inspection_results DROP CONSTRAINT inspection_results_response_time_check');
            DB::statement('ALTER TABLE inspection_results DROP CONSTRAINT inspection_results_outcome_verdict_check');
            DB::statement('ALTER TABLE inspection_results DROP CONSTRAINT inspection_results_outcome_check');
        }

        // Timeouts have no verdict, so a rollback to the live schema would be
        // invalid with them present. They carry no answer and are drop-safe.
        DB::table('inspection_results')->whereNull('verdict')->delete();

        Schema::table('inspection_results', function (Blueprint $table) {
            $table->string('verdict', 10)->nullable(false)->change();
            $table->dropColumn(['outcome', 'response_time_ms', 'answered_at']);
        });

        Schema::table('session_participants', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'completed_at']);
        });

        Schema::table('session_samples', function (Blueprint $table) {
            $table->dropUnique('session_samples_position_unique');
        });

        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->dropColumn(['response_time_limit_ms', 'started_at', 'completed_at']);
        });
    }
};

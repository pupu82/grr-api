<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trials happen outside the application: the app only stores verdicts.
     * There is no in-app trial lifecycle, so no start/submit state.
     */
    public function up(): void
    {
        Schema::dropIfExists('inspector_trials');
    }

    /**
     * Restore the table shape that existed before the drop.
     */
    public function down(): void
    {
        Schema::create('inspector_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('session_participants')->restrictOnDelete();
            $table->unsignedTinyInteger('trial_number');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'trial_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inspector_trials ADD CONSTRAINT inspector_trials_trial_number_check CHECK (trial_number BETWEEN 1 AND 3)');
        }
    }
};

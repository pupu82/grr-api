<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspector_trials');
    }
};

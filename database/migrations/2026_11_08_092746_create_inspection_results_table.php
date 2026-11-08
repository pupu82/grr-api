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
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('session_participants')->restrictOnDelete();
            $table->foreignId('session_sample_id')->constrained('session_samples')->restrictOnDelete();
            $table->unsignedTinyInteger('trial_number');
            $table->string('verdict', 10);
            $table->timestamps();
 
            $table->unique(['participant_id', 'session_sample_id', 'trial_number'], 'inspection_results_unique_answer');
            $table->index('session_sample_id');
        });
 
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inspection_results ADD CONSTRAINT inspection_results_verdict_check CHECK (verdict IN ('good', 'defect'))");
            DB::statement('ALTER TABLE inspection_results ADD CONSTRAINT inspection_results_trial_number_check CHECK (trial_number BETWEEN 1 AND 3)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};

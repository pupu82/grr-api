<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grr_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->unique()->constrained('session_participants')->restrictOnDelete();
            $table->decimal('repeatability', 5, 2);
            $table->decimal('effectiveness', 5, 2);
            $table->decimal('miss_rate', 5, 2)->nullable();
            $table->decimal('false_alarm_rate', 5, 2)->nullable();
            $table->string('judgement', 30);
            $table->json('thresholds');
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grr_evaluations');
    }
};

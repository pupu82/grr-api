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
        Schema::create('session_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->unique()->constrained('inspection_sessions')->restrictOnDelete();
            $table->decimal('between_agreement', 5, 2);
            $table->decimal('all_vs_standard', 5, 2);
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_evaluations');
    }
};

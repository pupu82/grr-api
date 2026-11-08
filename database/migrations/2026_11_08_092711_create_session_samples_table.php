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
        Schema::create('session_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('inspection_sessions')->restrictOnDelete();
            $table->foreignId('sample_id')->constrained()->restrictOnDelete();
            $table->string('answer_key', 10);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
 
            $table->unique(['session_id', 'sample_id']);
            $table->index('sample_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE session_samples ADD CONSTRAINT session_samples_answer_key_check CHECK (answer_key IN ('good', 'defect'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_samples');
    }
};

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
        Schema::create('inspection_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token', 20)->unique();
            $table->foreignId('plant_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('part_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('session_date');
            $table->unsignedTinyInteger('trial_count');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
 
            $table->index(['plant_id', 'session_date']);
            $table->index('team_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inspection_sessions ADD CONSTRAINT inspection_sessions_trial_count_check CHECK (trial_count BETWEEN 1 AND 3)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_sessions');
    }
};

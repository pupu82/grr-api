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
        Schema::create('sample_key_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->restrictOnDelete();
            $table->string('old_key', 10);
            $table->string('new_key', 10);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
 
            $table->index('sample_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sample_key_histories ADD CONSTRAINT sample_key_histories_keys_check CHECK (old_key IN ('good', 'defect') AND new_key IN ('good', 'defect'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sample_key_histories');
    }
};

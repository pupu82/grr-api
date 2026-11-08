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
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->foreignId('part_type_id')->constrained()->restrictOnDelete();
            $table->string('answer_key', 10);
            $table->timestamps();
            $table->softDeletes();

            // $table->string('active_code', 30)
            //     ->nullable()
            //     ->storedAs('CASE WHEN deleted_at IS NULL THEN code END')
            //     ->unique('samples_active_code_unique');

            $table->index('part_type_id');
        });

        DB::statement('CREATE UNIQUE INDEX samples_code_unique ON samples (code) WHERE deleted_at IS NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE samples ADD CONSTRAINT samples_answer_key_check CHECK (answer_key IN ('good', 'defect'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the part type on each copied sample so historical reports do not
     * change when a sample is later reassigned to another part type.
     */
    public function up(): void
    {
        Schema::table('session_samples', function (Blueprint $table): void {
            $table->foreignId('part_type_id')->nullable()->after('sample_id');
            $table->string('part_type_name', 50)->nullable()->after('part_type_id');
            $table->index(['session_id', 'part_type_id']);
        });

        DB::table('session_samples')
            ->select(['id', 'sample_id'])
            ->orderBy('id')
            ->chunkById(200, function ($sessionSamples): void {
                foreach ($sessionSamples as $sessionSample) {
                    $snapshot = DB::table('samples')
                        ->join('part_types', 'part_types.id', '=', 'samples.part_type_id')
                        ->where('samples.id', $sessionSample->sample_id)
                        ->select(['samples.part_type_id', 'part_types.name as part_type_name'])
                        ->first();

                    if ($snapshot === null) {
                        continue;
                    }

                    DB::table('session_samples')
                        ->where('id', $sessionSample->id)
                        ->update([
                            'part_type_id' => $snapshot->part_type_id,
                            'part_type_name' => $snapshot->part_type_name,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('session_samples', function (Blueprint $table): void {
            $table->dropIndex(['session_id', 'part_type_id']);
            $table->dropColumn(['part_type_id', 'part_type_name']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record every part type represented by a session. The primary part type
     * stays on inspection_sessions.part_type_id for backward compatibility.
     */
    public function up(): void
    {
        Schema::create('inspection_session_part_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('inspection_sessions')->cascadeOnDelete();
            $table->foreignId('part_type_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'part_type_id']);
            $table->index('part_type_id');
        });

        DB::table('inspection_sessions')
            ->select(['id', 'part_type_id'])
            ->orderBy('id')
            ->chunkById(200, function ($sessions): void {
                foreach ($sessions as $session) {
                    $partTypeIds = collect([$session->part_type_id])
                        ->merge(
                            DB::table('session_samples')
                                ->join('samples', 'samples.id', '=', 'session_samples.sample_id')
                                ->where('session_samples.session_id', $session->id)
                                ->distinct()
                                ->pluck('samples.part_type_id'),
                        )
                        ->filter()
                        ->unique()
                        ->values();

                    if ($partTypeIds->isEmpty()) {
                        continue;
                    }

                    DB::table('inspection_session_part_types')->insertOrIgnore(
                        $partTypeIds->map(fn (int $partTypeId): array => [
                            'session_id' => $session->id,
                            'part_type_id' => $partTypeId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->all(),
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_session_part_types');
    }
};

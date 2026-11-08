<?php

namespace App\Actions\MasterData;

use App\Models\Sample;
use App\Models\SampleKeyHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateSample
{
    /**
     * Update a sample and record a history row whenever its answer key changes.
     * Existing session_samples keep the key they were created with.
     *
     * @param  array{code?: string, part_type_id?: int, answer_key?: string}  $attributes
     */
    public function handle(Sample $sample, array $attributes, User $actor): Sample
    {
        return DB::transaction(function () use ($sample, $attributes, $actor): Sample {
            $oldKey = $sample->answer_key;

            $sample->update($attributes);

            $newKey = $sample->answer_key;

            if ($newKey !== $oldKey) {
                SampleKeyHistory::create([
                    'sample_id' => $sample->id,
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'changed_by' => $actor->id,
                    'changed_at' => now(),
                ]);
            }

            return $sample;
        });
    }
}

<?php

namespace App\Actions\Sessions;

use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Sample;
use Illuminate\Support\Collection;

/**
 * Copy the selected samples, their answer keys and their part types into a
 * draft session. The primary part type is always included in the pivot so the
 * legacy session fields remain meaningful.
 */
class SnapshotSessionSamples
{
    /**
     * @param  array<int, int>  $sampleIds
     */
    public function handle(InspectionSession $session, array $sampleIds): void
    {
        $samples = Sample::query()
            ->whereIn('id', $sampleIds)
            ->get()
            ->keyBy('id');

        $partTypes = PartType::query()
            ->withTrashed()
            ->whereIn('id', $samples->pluck('part_type_id'))
            ->get()
            ->keyBy('id');

        $partTypeIds = collect([$session->part_type_id]);

        $session->sessionSamples()->delete();

        foreach (array_values($sampleIds) as $index => $sampleId) {
            $sample = $samples->get($sampleId);

            $session->sessionSamples()->create([
                'sample_id' => $sample->id,
                'part_type_id' => $sample->part_type_id,
                'part_type_name' => $partTypes->get($sample->part_type_id)->name,
                'answer_key' => $sample->answer_key,
                'position' => $index + 1,
            ]);

            $partTypeIds->add($sample->part_type_id);
        }

        $session->partTypes()->sync($this->uniquePartTypeIds($partTypeIds));
    }

    /**
     * @param  Collection<int, int>  $partTypeIds
     * @return array<int, int>
     */
    private function uniquePartTypeIds(Collection $partTypeIds): array
    {
        return $partTypeIds
            ->filter()
            ->unique()
            ->values()
            ->map(fn (mixed $partTypeId): int => (int) $partTypeId)
            ->all();
    }
}

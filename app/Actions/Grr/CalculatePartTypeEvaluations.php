<?php

namespace App\Actions\Grr;

use App\Models\InspectionSession;
use App\Models\PartType;
use Illuminate\Support\Collection;

/**
 * Scope the session's computed attribute agreement to each part type it
 * contains. The session-level totals stay unchanged for existing clients.
 */
class CalculatePartTypeEvaluations
{
    public function __construct(
        private readonly CalculateAttributeAgreement $attributeAgreement,
        private readonly CalculateSessionAgreement $sessionAgreement,
    ) {}

    /**
     * @param  Collection<int, ParticipantAttributeAgreement>  $evaluations
     * @return Collection<int, PartTypeAttributeEvaluation>
     */
    public function handle(InspectionSession $session, Collection $evaluations): Collection
    {
        $session->loadMissing('partTypes');

        $samplesByPartType = $session->sessionSamples()->get()->groupBy('part_type_id');

        return $session->partTypes
            ->sortBy(fn (PartType $partType): array => [
                $partType->id === $session->part_type_id ? 0 : 1,
                $partType->name,
            ])
            ->map(function (PartType $partType) use ($session, $evaluations, $samplesByPartType): PartTypeAttributeEvaluation {
                $samples = $samplesByPartType->get($partType->id, collect());

                return new PartTypeAttributeEvaluation(
                    partType: $partType,
                    partTypeName: $samples->first()?->part_type_name ?? $partType->name,
                    isPrimary: $partType->id === $session->part_type_id,
                    samplesCount: $samples->count(),
                    evaluations: $evaluations->map(
                        fn (ParticipantAttributeAgreement $evaluation): ParticipantAttributeAgreement => new ParticipantAttributeAgreement(
                            $evaluation->participant,
                            $this->attributeAgreement->handle($evaluation->participant, $partType->id),
                        ),
                    ),
                    agreement: $this->sessionAgreement->handle($session, $partType->id),
                );
            })
            ->values();
    }
}

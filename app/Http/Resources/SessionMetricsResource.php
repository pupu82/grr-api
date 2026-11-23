<?php

namespace App\Http\Resources;

use App\Actions\Grr\ParticipantAttributeAgreement;
use App\Actions\Grr\PartTypeAttributeEvaluation;
use App\Actions\Grr\SessionAgreementResult;
use App\Models\InspectionSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The computed evaluation of a finished session: attribute agreement metrics
 * per participant, the session-level agreement between completed participants,
 * and the same metrics scoped to each part type. Nothing here is persisted, and
 * no judgement, repeatability or threshold is included.
 *
 * Decimal metrics are strings with exactly two decimals, matching the
 * `decimal:2` cast. `null` means the rate has no population to divide by.
 *
 * @mixin InspectionSession
 */
class SessionMetricsResource extends JsonResource
{
    /**
     * @param  Collection<int, ParticipantAttributeAgreement>  $evaluations
     * @param  Collection<int, PartTypeAttributeEvaluation>  $partTypeEvaluations
     */
    public function __construct(
        InspectionSession $session,
        private readonly Collection $evaluations,
        private readonly SessionAgreementResult $agreement,
        private readonly Collection $partTypeEvaluations,
    ) {
        parent::__construct($session);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'session' => [
                'id' => $this->uuid,
                'token' => $this->token,
                'status' => $this->status->value,
                'session_date' => $this->session_date?->toDateString(),
                'part_type_id' => $this->part_type_id,
                'part_type_ids' => $this->whenLoaded('partTypes', fn () => $this->partTypes->pluck('id')->all()),
                'response_time_limit_ms' => $this->response_time_limit_ms,
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'samples_count' => $this->whenCounted('sessionSamples'),
                'participants_count' => $this->whenCounted('participants'),
            ],
            'participants' => $this->evaluations
                ->map(fn (ParticipantAttributeAgreement $evaluation): array => $this->participantArray($evaluation))
                ->values()
                ->all(),
            'agreement' => $this->agreementArray($this->agreement),
            'part_types' => $this->partTypeEvaluations
                ->map(fn (PartTypeAttributeEvaluation $partType): array => [
                    'part_type_id' => $partType->partType->id,
                    'part_type_name' => $partType->partTypeName,
                    'is_primary' => $partType->isPrimary,
                    'samples_count' => $partType->samplesCount,
                    'participants' => $partType->evaluations
                        ->map(fn (ParticipantAttributeAgreement $evaluation): array => $this->participantArray($evaluation))
                        ->values()
                        ->all(),
                    'agreement' => $this->agreementArray($partType->agreement),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function participantArray(ParticipantAttributeAgreement $evaluation): array
    {
        return [
            'participant_id' => $evaluation->participant->id,
            'user' => [
                'id' => $evaluation->participant->user?->uuid,
                'employee_code' => $evaluation->participant->user?->employee_code,
                'name' => $evaluation->participant->user?->name,
            ],
            'started_at' => $evaluation->participant->started_at?->toIso8601String(),
            'completed_at' => $evaluation->participant->completed_at?->toIso8601String(),
            'is_complete' => $evaluation->participant->completed_at !== null,
            'metrics' => [
                'effectiveness' => $this->decimal($evaluation->metrics->effectiveness),
                'miss_rate' => $this->decimal($evaluation->metrics->missRate),
                'false_alarm_rate' => $this->decimal($evaluation->metrics->falseAlarmRate),
                'decisions' => $evaluation->metrics->decisions,
                'correct_decisions' => $evaluation->metrics->correctDecisions,
                'defect_decisions' => $evaluation->metrics->defectDecisions,
                'good_decisions' => $evaluation->metrics->goodDecisions,
                'missed_defects' => $evaluation->metrics->missedDefects,
                'false_alarms' => $evaluation->metrics->falseAlarms,
                'timed_out_samples' => $evaluation->metrics->timedOutSamples,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function agreementArray(SessionAgreementResult $agreement): array
    {
        return [
            'participants_compared' => $agreement->participantsCompared,
            'compared_samples' => $agreement->comparedSamples,
            'excluded_samples' => $agreement->excludedSamples,
            'between_agreement' => $this->decimal($agreement->betweenAgreement),
            'all_vs_standard' => $this->decimal($agreement->allVsStandard),
            'judgement' => $agreement->judgement?->value,
        ];
    }

    /**
     * Serialize a percentage with two decimals, or `null` when it has no
     * population.
     */
    private function decimal(?float $value): ?string
    {
        return $value === null ? null : number_format($value, 2, '.', '');
    }
}

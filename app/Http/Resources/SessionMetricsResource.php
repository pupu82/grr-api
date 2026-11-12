<?php

namespace App\Http\Resources;

use App\Actions\Grr\ParticipantAttributeAgreement;
use App\Actions\Grr\SessionAgreementResult;
use App\Models\InspectionSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The computed evaluation of a finished session: attribute agreement metrics
 * per participant and the session-level agreement between completed
 * participants. Nothing here is persisted, and no judgement, repeatability or
 * threshold is included.
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
     */
    public function __construct(
        InspectionSession $session,
        private readonly Collection $evaluations,
        private readonly SessionAgreementResult $agreement,
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
                'response_time_limit_ms' => $this->response_time_limit_ms,
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'samples_count' => $this->whenCounted('sessionSamples'),
                'participants_count' => $this->whenCounted('participants'),
            ],
            'participants' => $this->evaluations->map(
                fn (ParticipantAttributeAgreement $evaluation): array => [
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
                ],
            )->values()->all(),
            'agreement' => [
                'participants_compared' => $this->agreement->participantsCompared,
                'compared_samples' => $this->agreement->comparedSamples,
                'excluded_samples' => $this->agreement->excludedSamples,
                'between_agreement' => $this->decimal($this->agreement->betweenAgreement),
                'all_vs_standard' => $this->decimal($this->agreement->allVsStandard),
                'judgement' => $this->agreement->judgement?->value,
            ],
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

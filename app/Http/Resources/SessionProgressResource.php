<?php

namespace App\Http\Resources;

use App\Enums\SessionStatus;
use App\Models\InspectionResult;
use App\Models\SessionParticipant;
use App\Models\SessionSample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What one inspector sees while running their part of a session: where they
 * are, which sample comes next and, after a submission, the stored answer.
 *
 * The answer key is never part of this payload.
 *
 * @mixin SessionParticipant
 */
class SessionProgressResource extends JsonResource
{
    public function __construct(
        SessionParticipant $participant,
        private readonly ?SessionSample $currentSample,
        private readonly int $total,
        private readonly int $answeredCount,
        private readonly ?InspectionResult $result = null,
        private readonly ?bool $resultCreated = null,
    ) {
        parent::__construct($participant);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->session;

        // A closed session offers no next sample, even when the participant
        // still has unanswered samples; those are recorded as missing.
        $currentSample = $session->status === SessionStatus::InProgress
            ? $this->currentSample
            : null;

        return [
            'session' => [
                'id' => $session->uuid,
                'token' => $session->token,
                'status' => $session->status->value,
                'response_time_limit_ms' => $session->response_time_limit_ms,
                'completed_at' => $session->completed_at?->toIso8601String(),
            ],
            'participant' => [
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'answered_count' => $this->answeredCount,
                'samples_count' => $this->total,
                'is_complete' => $this->completed_at !== null,
            ],
            'current_sample' => $currentSample === null ? null : [
                'id' => $currentSample->id,
                'sample_id' => $currentSample->sample_id,
                'code' => $currentSample->sample?->code,
                'position' => $currentSample->position,
            ],
            'result' => $this->result === null ? null : new InspectionResultResource($this->result),
            'result_created' => $this->resultCreated,
        ];
    }
}

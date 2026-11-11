<?php

namespace App\Http\Resources;

use App\Models\InspectionSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every stored outcome of a session, grouped by participant, plus the sample
 * snapshot and its answer key for admins and managers.
 *
 * Each result is one real-inspection outcome. A `timed_out` result has no
 * verdict; it is missing data, never `good` or `defect`.
 *
 * @mixin InspectionSession
 */
class SessionResultsResource extends JsonResource
{
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
            'samples' => SessionSampleResource::collection($this->whenLoaded('sessionSamples')),
            'participants' => SessionParticipantResource::collection($this->whenLoaded('participants')),
        ];
    }
}

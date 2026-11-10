<?php

namespace App\Http\Resources;

use App\Models\InspectionSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InspectionSession */
class InspectionSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'token' => $this->token,
            'plant_id' => $this->plant_id,
            'plant' => $this->whenLoaded('plant', fn () => $this->plant === null ? null : new PlantResource($this->plant)),
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : new TeamResource($this->team)),
            'part_type_id' => $this->part_type_id,
            'part_type' => $this->whenLoaded('partType', fn () => $this->partType === null ? null : new PartTypeResource($this->partType)),
            'session_date' => $this->session_date?->toDateString(),
            'status' => $this->status->value,
            'response_time_limit_ms' => $this->response_time_limit_ms,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'samples_count' => $this->whenCounted('sessionSamples'),
            'participants_count' => $this->whenCounted('participants'),
            'session_samples' => SessionSampleResource::collection($this->whenLoaded('sessionSamples')),
            'participants' => SessionParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

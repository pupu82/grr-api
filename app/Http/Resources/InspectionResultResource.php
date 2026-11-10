<?php

namespace App\Http\Resources;

use App\Models\InspectionResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InspectionResult */
class InspectionResultResource extends JsonResource
{
    /**
     * `verdict` is `null` for a timed-out sample. The answer key is never
     * exposed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_sample_id' => $this->session_sample_id,
            'position' => $this->whenLoaded('sessionSample', fn (): ?int => $this->sessionSample?->position),
            'outcome' => $this->outcome->value,
            'verdict' => $this->verdict?->value,
            'response_time_ms' => $this->response_time_ms,
            'answered_at' => $this->answered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

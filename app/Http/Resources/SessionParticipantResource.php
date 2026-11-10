<?php

namespace App\Http\Resources;

use App\Models\SessionParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SessionParticipant */
class SessionParticipantResource extends JsonResource
{
    /**
     * Inspectors are exposed by public uuid, never by the internal bigint id.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->uuid,
                'employee_code' => $this->user->employee_code,
                'name' => $this->user->name,
            ]),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'results_count' => $this->whenCounted('results'),
            'is_complete' => $this->completed_at !== null,
        ];
    }
}

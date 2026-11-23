<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActivityLog */
class ActivityLogResource extends JsonResource
{
    /**
     * The actor is identified by public uuid, never by the internal bigint id.
     * `actor_name` is a snapshot, so the entry still reads correctly after a
     * rename or a soft delete.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'actor' => $this->whenLoaded('actor', fn (): ?array => $this->actor === null ? null : [
                'id' => $this->actor->uuid,
                'name' => $this->actor->name,
            ]),
            'actor_name' => $this->actor_name,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'context' => $this->context ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

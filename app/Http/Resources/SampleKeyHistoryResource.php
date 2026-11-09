<?php

namespace App\Http\Resources;

use App\Models\SampleKeyHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SampleKeyHistory */
class SampleKeyHistoryResource extends JsonResource
{
    /**
     * The actor is exposed by public uuid, never by the internal bigint id.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sample_id' => $this->sample_id,
            'old_key' => $this->old_key->value,
            'new_key' => $this->new_key->value,
            'changed_by' => $this->whenLoaded('changer', fn (): ?string => $this->changer?->uuid),
            'changed_by_name' => $this->whenLoaded('changer', fn (): ?string => $this->changer?->name),
            'changed_at' => $this->changed_at?->toIso8601String(),
        ];
    }
}

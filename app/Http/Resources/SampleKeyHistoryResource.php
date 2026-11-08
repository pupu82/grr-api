<?php

namespace App\Http\Resources;

use App\Models\SampleKeyHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SampleKeyHistory */
class SampleKeyHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sample_id' => $this->sample_id,
            'old_key' => $this->old_key->value,
            'new_key' => $this->new_key->value,
            'changed_by' => $this->changed_by,
            'changed_at' => $this->changed_at?->toIso8601String(),
        ];
    }
}

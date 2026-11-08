<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Sample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sample */
class SampleResource extends JsonResource
{
    /**
     * Answer keys are never exposed to inspectors.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'part_type_id' => $this->part_type_id,
            'part_type' => new PartTypeResource($this->whenLoaded('partType')),
            'answer_key' => $this->when(
                $request->user()?->role !== Role::Inspector,
                $this->answer_key->value,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

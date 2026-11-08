<?php

namespace App\Http\Resources;

use App\Models\PartType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PartType */
class PartTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'samples_count' => $this->whenCounted('samples'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

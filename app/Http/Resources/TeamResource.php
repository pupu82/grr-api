<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'plant_id' => $this->plant_id,
            'plant' => new PlantResource($this->whenLoaded('plant')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The bigint primary key is never exposed: `id` is the public uuid.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'registered_via' => $this->registered_via->value,
            'must_change_password' => $this->must_change_password,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'team' => $this->whenLoaded('team', fn (): ?array => $this->team === null ? null : [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

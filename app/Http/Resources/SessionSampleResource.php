<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\SessionSample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SessionSample */
class SessionSampleResource extends JsonResource
{
    /**
     * The snapshotted answer key is only exposed to admins and managers.
     * Inspectors must never receive it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeAnswerKey = in_array(
            $request->user()?->role,
            [Role::Admin, Role::Manager],
            strict: true,
        );

        return [
            'id' => $this->id,
            'sample_id' => $this->sample_id,
            'code' => $this->whenLoaded('sample', fn (): ?string => $this->sample?->code),
            'position' => $this->position,
            'answer_key' => $this->when($canSeeAnswerKey, fn (): string => $this->answer_key->value),
        ];
    }
}

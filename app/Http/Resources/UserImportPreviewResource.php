<?php

namespace App\Http\Resources;

use App\Models\UserImportBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserImportBatch
 */
class UserImportPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'filename' => $this->filename,
            'counts' => $this->counts,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'rows' => $this->rows,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The result of confirming an import. Temporary passwords are part of this
 * response only and are never written to the import batch or the audit log.
 */
class UserImportResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'import_batch_id' => $this->resource['import_batch_id'],
            'counts' => $this->resource['counts'],
            'results' => $this->resource['results'],
        ];
    }
}

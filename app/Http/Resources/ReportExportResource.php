<?php

namespace App\Http\Resources;

use App\Enums\ExportStatus;
use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportExport
 */
class ReportExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'session_id' => $this->whenLoaded('session', fn (): string => $this->session->uuid),
            'format' => $this->format->value,
            'status' => $this->status->value,
            'file_name' => $this->file_name,
            'error' => $this->when($this->status === ExportStatus::Failed, fn (): ?string => $this->error),
            'download_url' => $this->when(
                $this->status === ExportStatus::Completed && $this->file_name !== null,
                fn (): string => route('exports.download', $this->uuid),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}

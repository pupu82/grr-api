<?php

namespace Database\Factories;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\InspectionSession;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => InspectionSession::factory(),
            'requested_by' => User::factory(),
            'format' => ExportFormat::Xlsx,
            'status' => ExportStatus::Pending,
            'file_path' => null,
            'file_name' => null,
            'error' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Completed,
            'file_path' => 'evaluation-exports/report.xlsx',
            'file_name' => 'grr-evaluation.xlsx',
            'completed_at' => now(),
        ]);
    }
}

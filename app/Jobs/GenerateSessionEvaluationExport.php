<?php

namespace App\Jobs;

use App\Actions\Grr\CalculatePartTypeEvaluations;
use App\Actions\Grr\CalculateSessionAgreement;
use App\Actions\Grr\CalculateSessionEvaluation;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Exports\SessionEvaluationExport;
use App\Models\ReportExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Throwable;

class GenerateSessionEvaluationExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly ReportExport $export) {}

    public function handle(
        CalculateSessionEvaluation $calculateEvaluation,
        CalculateSessionAgreement $calculateAgreement,
        CalculatePartTypeEvaluations $calculatePartTypes,
        Excel $excel,
    ): void {
        if ($this->export->status === ExportStatus::Completed) {
            return;
        }

        $this->export->update(['status' => ExportStatus::Processing]);

        try {
            $session = $this->export->session()->firstOrFail();

            $evaluations = $calculateEvaluation->handle($session);

            $export = new SessionEvaluationExport(
                $session,
                $evaluations,
                $calculateAgreement->handle($session),
                $calculatePartTypes->handle($session, $evaluations),
            );

            $format = $this->export->format;
            $extension = $format->value;
            $fileName = $this->export->file_name
                ?? sprintf('grr-evaluation-%s.%s', Str::slug($session->token), $extension);
            $path = sprintf(
                '%s/%s-%s.%s',
                rtrim((string) config('grr.exports.directory'), '/'),
                Str::slug($session->token),
                $this->export->uuid,
                $extension,
            );

            $excel->store(
                $export,
                $path,
                config('grr.exports.disk'),
                $format === ExportFormat::Xlsx ? Excel::XLSX : Excel::CSV,
            );

            $this->export->update([
                'status' => ExportStatus::Completed,
                'file_path' => $path,
                'file_name' => $fileName,
                'error' => null,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->export->update([
                'status' => ExportStatus::Failed,
                'error' => 'The report could not be generated.',
            ]);
        }
    }
}

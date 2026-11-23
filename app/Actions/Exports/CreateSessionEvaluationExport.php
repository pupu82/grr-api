<?php

namespace App\Actions\Exports;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Enums\SessionStatus;
use App\Jobs\GenerateSessionEvaluationExport;
use App\Models\InspectionSession;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates the export record and queues the file generation.
 */
class CreateSessionEvaluationExport
{
    public function handle(User $actor, InspectionSession $session, ExportFormat $format): ReportExport
    {
        if ($session->status !== SessionStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => 'Only a completed session can be exported.',
            ]);
        }

        $export = ReportExport::create([
            'session_id' => $session->id,
            'requested_by' => $actor->id,
            'format' => $format,
            'status' => ExportStatus::Pending,
            'file_name' => sprintf('grr-evaluation-%s.%s', Str::slug($session->token), $format->value),
        ]);

        GenerateSessionEvaluationExport::dispatch($export);

        return $export->load('session');
    }
}

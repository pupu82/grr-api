<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteInspectionSession
{
    /**
     * Only a draft session can be deleted. An inspection that has started is
     * part of the record and must not disappear.
     */
    public function handle(InspectionSession $session): void
    {
        if ($session->status !== SessionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft session can be deleted.',
            ]);
        }

        DB::transaction(function () use ($session): void {
            $session->sessionSamples()->delete();
            $session->participants()->delete();
            $session->delete();
        });
    }
}

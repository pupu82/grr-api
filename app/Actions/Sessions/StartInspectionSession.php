<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartInspectionSession
{
    /**
     * Move a draft session to in_progress. Samples, participants and the time
     * limit are frozen from this point on.
     */
    public function handle(InspectionSession $session): InspectionSession
    {
        return DB::transaction(function () use ($session): InspectionSession {
            $locked = InspectionSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            if ($locked->status !== SessionStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft session can be started.',
                ]);
            }

            $locked->status = SessionStatus::InProgress;
            $locked->started_at = now();
            $locked->save();

            return $locked;
        });
    }
}

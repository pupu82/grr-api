<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseInspectionSession
{
    /**
     * Admin escape hatch for an inspector who started but will never finish.
     * Unfinished participants keep a null `completed_at` and are not
     * evaluated; no verdict or timeout is invented for them.
     */
    public function handle(InspectionSession $session): InspectionSession
    {
        return DB::transaction(function () use ($session): InspectionSession {
            $locked = InspectionSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            if ($locked->status !== SessionStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => 'Only an in-progress session can be closed.',
                ]);
            }

            $locked->status = SessionStatus::Completed;
            $locked->completed_at = now();
            $locked->save();

            return $locked;
        });
    }
}

<?php

namespace App\Actions\Sessions;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartInspectionSession
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Move a draft session to in_progress. Samples, participants and the time
     * limit are frozen from this point on.
     */
    public function handle(InspectionSession $session, User $actor): InspectionSession
    {
        return DB::transaction(function () use ($session, $actor): InspectionSession {
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

            $this->logActivity->handle(ActivityAction::SessionStarted, $actor, 'session', $locked->uuid);

            return $locked;
        });
    }
}

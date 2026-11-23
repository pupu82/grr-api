<?php

namespace App\Actions\Sessions;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseInspectionSession
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Admin escape hatch for an inspector who started but will never finish.
     * Unfinished participants keep a null `completed_at` and are not
     * evaluated; no verdict or timeout is invented for them.
     */
    public function handle(InspectionSession $session, User $actor): InspectionSession
    {
        return DB::transaction(function () use ($session, $actor): InspectionSession {
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

            $this->logActivity->handle(ActivityAction::SessionClosed, $actor, 'session', $locked->uuid);

            return $locked;
        });
    }
}

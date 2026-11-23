<?php

namespace App\Actions\Sessions;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteInspectionSession
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Only a draft session can be deleted. An inspection that has started is
     * part of the record and must not disappear.
     */
    public function handle(InspectionSession $session, User $actor): void
    {
        if ($session->status !== SessionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft session can be deleted.',
            ]);
        }

        DB::transaction(function () use ($session, $actor): void {
            $session->sessionSamples()->delete();
            $session->participants()->delete();
            $session->delete();

            $this->logActivity->handle(
                ActivityAction::SessionDeleted,
                $actor,
                'session',
                $session->uuid,
                ['session_date' => $session->session_date->toDateString()],
            );
        });
    }
}

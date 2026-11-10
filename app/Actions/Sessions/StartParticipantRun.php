<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Models\SessionParticipant;
use Illuminate\Validation\ValidationException;

class StartParticipantRun
{
    /**
     * Start one inspector's run inside an already started session. Calling it
     * again is harmless: the original start time is kept.
     */
    public function handle(SessionParticipant $participant): SessionParticipant
    {
        $session = $participant->session()->firstOrFail();

        if ($session->status !== SessionStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => 'The session is not accepting answers.',
            ]);
        }

        if ($participant->started_at !== null) {
            return $participant;
        }

        $participant->started_at = now();
        $participant->save();

        return $participant;
    }
}

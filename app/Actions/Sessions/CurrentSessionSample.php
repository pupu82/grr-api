<?php

namespace App\Actions\Sessions;

use App\Models\SessionParticipant;
use App\Models\SessionSample;

class CurrentSessionSample
{
    /**
     * The first sample of the session that this participant has not answered
     * or timed out yet. Answers are strictly sequential, so this is the only
     * sample the participant may submit next.
     *
     * @return array{sample: ?SessionSample, total: int}
     */
    public function handle(SessionParticipant $participant): array
    {
        $session = $participant->session;

        $answeredSampleIds = $participant->results()->select('session_sample_id');

        $sample = $session->sessionSamples()
            ->whereNotIn('id', $answeredSampleIds)
            ->orderBy('position')
            ->with('sample')
            ->first();

        return [
            'sample' => $sample,
            'total' => $session->sessionSamples()->count(),
        ];
    }
}

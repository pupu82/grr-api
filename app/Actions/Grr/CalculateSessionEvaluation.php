<?php

namespace App\Actions\Grr;

use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use App\Models\SessionParticipant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Compute the attribute agreement metrics of every participant of a finished
 * session. Results are immutable once a session is completed, so the numbers
 * are stable and can be recomputed on demand.
 *
 * Nothing is persisted and no judgement is attached: the criteria, the
 * repeatability definition and the session-level agreement formulas are not
 * confirmed yet.
 */
class CalculateSessionEvaluation
{
    public function __construct(private readonly CalculateAttributeAgreement $agreement) {}

    /**
     * @return Collection<int, ParticipantAttributeAgreement>
     */
    public function handle(InspectionSession $session): Collection
    {
        if ($session->status !== SessionStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => 'Only a completed session can be evaluated.',
            ]);
        }

        return $session->participants()
            ->with([
                // A deleted inspector still appears in historical reports.
                'user' => fn ($query) => $query->withTrashed(),
                'results' => fn ($query) => $query->with('sessionSample'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (SessionParticipant $participant): ParticipantAttributeAgreement => new ParticipantAttributeAgreement(
                $participant,
                $this->agreement->handle($participant),
            ));
    }
}

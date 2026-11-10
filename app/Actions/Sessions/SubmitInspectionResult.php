<?php

namespace App\Actions\Sessions;

use App\Enums\AnswerOutcome;
use App\Enums\SessionStatus;
use App\Enums\Verdict;
use App\Models\InspectionResult;
use App\Models\InspectionSession;
use App\Models\SessionParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitInspectionResult
{
    public function __construct(private CurrentSessionSample $currentSample) {}

    /**
     * Store one inspector outcome for one sample: a verdict or a timeout.
     *
     * The same payload may be submitted again after an offline retry; it is
     * then returned unchanged. A different payload for an already answered
     * sample is rejected.
     *
     * @param  array{
     *     session_sample_id: int,
     *     outcome: string,
     *     verdict?: string|null,
     *     response_time_ms: int,
     *     answered_at: string
     * }  $attributes
     */
    public function handle(SessionParticipant $participant, array $attributes): InspectionResult
    {
        return DB::transaction(function () use ($participant, $attributes): InspectionResult {
            $session = InspectionSession::query()
                ->lockForUpdate()
                ->findOrFail($participant->session_id);

            $sample = $session->sessionSamples()
                ->whereKey($attributes['session_sample_id'])
                ->first();

            if ($sample === null) {
                throw ValidationException::withMessages([
                    'session_sample_id' => 'The sample does not belong to this session.',
                ]);
            }

            $outcome = AnswerOutcome::from($attributes['outcome']);
            $verdict = $outcome === AnswerOutcome::Answered
                ? Verdict::from($attributes['verdict'])
                : null;

            // An offline retry may arrive after the session has completed.
            // A repeat of an accepted answer is still answered with that
            // record, so the queue can drain without inventing a conflict.
            $existing = $participant->results()
                ->where('session_sample_id', $sample->id)
                ->first();

            if ($existing !== null) {
                return $this->keepIdempotent($existing, $outcome, $verdict, $attributes);
            }

            if ($session->status !== SessionStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => 'The session is not accepting answers.',
                ]);
            }

            if ($participant->started_at === null) {
                throw ValidationException::withMessages([
                    'started_at' => 'Start your run before submitting answers.',
                ]);
            }

            if ($outcome === AnswerOutcome::Answered
                && (int) $attributes['response_time_ms'] > $session->response_time_limit_ms) {
                throw ValidationException::withMessages([
                    'response_time_ms' => 'The response time exceeds the session limit.',
                ]);
            }

            $current = $this->currentSample->handle($participant)['sample'];

            if ($current === null || $current->id !== $sample->id) {
                throw ValidationException::withMessages([
                    'session_sample_id' => 'Answers must follow the sample order.',
                ]);
            }

            $result = $participant->results()->create([
                'session_sample_id' => $sample->id,
                'outcome' => $outcome,
                'verdict' => $verdict,
                'response_time_ms' => (int) $attributes['response_time_ms'],
                'answered_at' => $attributes['answered_at'],
            ]);

            $this->completeIfFinished($participant, $session);

            return $result;
        });
    }

    /**
     * @param  array{response_time_ms: int}  $attributes
     */
    private function keepIdempotent(
        InspectionResult $existing,
        AnswerOutcome $outcome,
        ?Verdict $verdict,
        array $attributes,
    ): InspectionResult {
        $same = $existing->outcome === $outcome
            && $existing->verdict === $verdict
            && $existing->response_time_ms === (int) $attributes['response_time_ms'];

        if ($same) {
            return $existing;
        }

        throw ValidationException::withMessages([
            'session_sample_id' => 'This sample has already been answered.',
        ]);
    }

    /**
     * Mark the participant finished when every sample has an outcome, then
     * complete the session when every participant is finished.
     */
    private function completeIfFinished(SessionParticipant $participant, InspectionSession $session): void
    {
        $total = $session->sessionSamples()->count();

        if ($participant->completed_at === null
            && $participant->results()->count() >= $total) {
            $participant->completed_at = now();
            $participant->save();
        }

        if ($session->participants()->whereNull('completed_at')->doesntExist()) {
            $session->status = SessionStatus::Completed;
            $session->completed_at = now();
            $session->save();
        }
    }
}

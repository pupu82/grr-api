<?php

namespace App\Actions\Grr;

use App\Enums\AnswerOutcome;
use App\Enums\Judgement;
use App\Models\InspectionSession;

/**
 * AIAG session-level attribute agreement between the appraisers who finished
 * the session, plus their agreement with the answer key.
 *
 * - Only participants with `completed_at` are compared. Fewer than two of them
 *   makes both rates undefined.
 * - A sample is excluded when any compared participant timed out on it, because
 *   a timeout is missing data, not a verdict.
 * - `between_agreement`: samples where every compared participant gave the same
 *   verdict, divided by the compared samples.
 * - `all_vs_standard`: samples where every compared participant gave the same
 *   verdict and that verdict matches the session sample's answer key, divided by
 *   the compared samples.
 * - `judgement`: derived from `allVsStandard` using the AIAG MSA criteria:
 *   - `> 90%` = `acceptable`
 *   - `80% – 90%` = `marginal`
 *   - `< 80%` = `unacceptable`
 *
 * Nothing is persisted. This is not Kappa and says nothing about repeatability.
 */
class CalculateSessionAgreement
{
    public function handle(InspectionSession $session): SessionAgreementResult
    {
        return $this->doHandle($session);
    }

    private function doHandle(InspectionSession $session): SessionAgreementResult
    {
        $participants = $session->participants()
            ->whereNotNull('completed_at')
            ->with('results')
            ->orderBy('id')
            ->get();

        $samples = $session->sessionSamples()
            ->orderBy('position')
            ->get();

        $resultsBySample = [];

        foreach ($participants as $participant) {
            foreach ($participant->results as $result) {
                $resultsBySample[$result->session_sample_id][$participant->id] = $result;
            }
        }

        $comparedSamples = 0;
        $excludedSamples = 0;
        $agreeingSamples = 0;
        $matchingSamples = 0;

        foreach ($samples as $sample) {
            $sampleResults = $resultsBySample[$sample->id] ?? [];
            $verdicts = [];
            $excluded = false;

            foreach ($participants as $participant) {
                $result = $sampleResults[$participant->id] ?? null;

                // A missing result on a completed participant is missing data,
                // exactly like a timeout.
                if ($result === null || $result->outcome === AnswerOutcome::TimedOut) {
                    $excluded = true;
                    break;
                }

                $verdicts[] = $result->verdict;
            }

            if ($excluded) {
                $excludedSamples++;

                continue;
            }

            $comparedSamples++;

            $first = $verdicts[0] ?? null;
            $allAgree = $first !== null;

            foreach ($verdicts as $verdict) {
                if ($verdict !== $first) {
                    $allAgree = false;
                    break;
                }
            }

            if (! $allAgree) {
                continue;
            }

            $agreeingSamples++;

            if ($first === $sample->answer_key) {
                $matchingSamples++;
            }
        }

        $comparable = $participants->count() >= 2 && $comparedSamples > 0;
        $allVsStandard = $comparable ? round($matchingSamples / $comparedSamples * 100, 2) : null;

        return new SessionAgreementResult(
            participantsCompared: $participants->count(),
            comparedSamples: $comparedSamples,
            excludedSamples: $excludedSamples,
            betweenAgreement: $comparable ? round($agreeingSamples / $comparedSamples * 100, 2) : null,
            allVsStandard: $allVsStandard,
            judgement: $this->determineJudgement($allVsStandard),
        );
    }

    private function determineJudgement(?float $allVsStandard): ?Judgement
    {
        if ($allVsStandard === null) {
            return null;
        }

        return match (true) {
            $allVsStandard > 90 => Judgement::Acceptable,
            $allVsStandard >= 80 => Judgement::Marginal,
            default => Judgement::Unacceptable,
        };
    }
}

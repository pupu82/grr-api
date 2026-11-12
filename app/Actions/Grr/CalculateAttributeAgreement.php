<?php

namespace App\Actions\Grr;

use App\Enums\AnswerOutcome;
use App\Enums\Verdict;
use App\Models\SessionParticipant;

/**
 * Attribute Agreement Analysis for one inspector, following the AIAG MSA
 * attribute study.
 *
 * - `miss_rate`: defect samples judged `good`, divided by every defect sample
 *   the inspector actually judged.
 * - `false_alarm_rate`: good samples judged `defect`, divided by every good
 *   sample the inspector actually judged.
 * - `effectiveness`: correct decisions divided by every decision.
 *
 * Deny the denominator instead of dividing by zero: a missing population yields
 * `null`. A timed-out sample has no verdict, so it is missing data and never
 * `good` or `defect`.
 */
class CalculateAttributeAgreement
{
    public function handle(SessionParticipant $participant): AttributeAgreementResult
    {
        $results = $participant->relationLoaded('results')
            ? $participant->results->loadMissing('sessionSample')
            : $participant->results()->with('sessionSample')->get();

        $decisions = $results->filter(
            fn ($result): bool => $result->outcome === AnswerOutcome::Answered && $result->verdict !== null,
        );

        $timedOutSamples = $results
            ->where('outcome', AnswerOutcome::TimedOut)
            ->count();

        $correctDecisions = 0;
        $missedDefects = 0;
        $falseAlarms = 0;
        $defectDecisions = 0;
        $goodDecisions = 0;

        foreach ($decisions as $decision) {
            $answerKey = $decision->sessionSample?->answer_key;

            if ($answerKey === Verdict::Defect) {
                $defectDecisions++;

                if ($decision->verdict === Verdict::Good) {
                    $missedDefects++;
                }
            }

            if ($answerKey === Verdict::Good) {
                $goodDecisions++;

                if ($decision->verdict === Verdict::Defect) {
                    $falseAlarms++;
                }
            }

            if ($decision->verdict === $answerKey) {
                $correctDecisions++;
            }
        }

        return new AttributeAgreementResult(
            effectiveness: $this->rate($correctDecisions, $decisions->count()),
            missRate: $this->rate($missedDefects, $defectDecisions),
            falseAlarmRate: $this->rate($falseAlarms, $goodDecisions),
            decisions: $decisions->count(),
            correctDecisions: $correctDecisions,
            defectDecisions: $defectDecisions,
            goodDecisions: $goodDecisions,
            missedDefects: $missedDefects,
            falseAlarms: $falseAlarms,
            timedOutSamples: $timedOutSamples,
        );
    }

    /**
     * A percentage with two decimals, or `null` when there is nothing to divide.
     */
    private function rate(int $part, int $total): ?float
    {
        if ($total === 0) {
            return null;
        }

        return round($part / $total * 100, 2);
    }
}

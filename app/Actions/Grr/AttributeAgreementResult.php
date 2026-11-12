<?php

namespace App\Actions\Grr;

/**
 * Attribute Agreement Analysis metrics for one inspector, as percentages with
 * two decimals. A `null` rate means the denominator is zero, not a zero rate.
 *
 * A timed-out sample has no verdict. It is missing data: it is never counted as
 * `good` or `defect` and never enters a denominator. `timedOutSamples` reports
 * how many such samples were left out.
 */
final readonly class AttributeAgreementResult
{
    public function __construct(
        public ?float $effectiveness,
        public ?float $missRate,
        public ?float $falseAlarmRate,
        public int $decisions,
        public int $correctDecisions,
        public int $defectDecisions,
        public int $goodDecisions,
        public int $missedDefects,
        public int $falseAlarms,
        public int $timedOutSamples,
    ) {}
}

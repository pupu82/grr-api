<?php

namespace App\Actions\Grr;

use App\Enums\Judgement;

/**
 * Session-level attribute agreement for the appraisers who finished the
 * session.
 *
 * A `null` rate means the comparison is undefined: fewer than two completed
 * participants, or no sample left to compare. A timed-out sample is missing
 * data, so it excludes the sample from both rates instead of being counted as
 * `good` or `defect`.
 *
 * `judgement` is derived from `allVsStandard` using the AIAG MSA criteria.
 */
final readonly class SessionAgreementResult
{
    public function __construct(
        public int $participantsCompared,
        public int $comparedSamples,
        public int $excludedSamples,
        public ?float $betweenAgreement,
        public ?float $allVsStandard,
        public ?Judgement $judgement,
    ) {}
}

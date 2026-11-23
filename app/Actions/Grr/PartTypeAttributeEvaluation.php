<?php

namespace App\Actions\Grr;

use App\Models\PartType;
use Illuminate\Support\Collection;

/**
 * One part type inside a multi-part-type session, with the same report shape as
 * the session-level evaluation scoped to that part type's samples.
 */
final readonly class PartTypeAttributeEvaluation
{
    /**
     * @param  Collection<int, ParticipantAttributeAgreement>  $evaluations
     */
    public function __construct(
        public PartType $partType,
        public string $partTypeName,
        public bool $isPrimary,
        public int $samplesCount,
        public Collection $evaluations,
        public SessionAgreementResult $agreement,
    ) {}
}

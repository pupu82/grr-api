<?php

namespace App\Actions\Grr;

use App\Models\SessionParticipant;

/**
 * One participant with their computed agreement metrics.
 */
final readonly class ParticipantAttributeAgreement
{
    public function __construct(
        public SessionParticipant $participant,
        public AttributeAgreementResult $metrics,
    ) {}
}

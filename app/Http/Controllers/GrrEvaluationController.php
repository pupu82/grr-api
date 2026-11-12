<?php

namespace App\Http\Controllers;

use App\Actions\Grr\CalculateSessionAgreement;
use App\Actions\Grr\CalculateSessionEvaluation;
use App\Http\Resources\SessionMetricsResource;
use App\Models\InspectionSession;

class GrrEvaluationController extends Controller
{
    /**
     * Compute the attribute agreement metrics of a completed session, per
     * participant and between participants. This is a report, so it is
     * readable by admins and managers only; an inspector keeps access to their
     * own stored results.
     */
    public function show(
        InspectionSession $session,
        CalculateSessionEvaluation $calculate,
        CalculateSessionAgreement $calculateAgreement,
    ): SessionMetricsResource {
        $evaluations = $calculate->handle($session);
        $agreement = $calculateAgreement->handle($session);

        $session->loadCount(['sessionSamples', 'participants']);

        return new SessionMetricsResource($session, $evaluations, $agreement);
    }
}

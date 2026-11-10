<?php

namespace App\Http\Controllers;

use App\Actions\Sessions\CurrentSessionSample;
use App\Actions\Sessions\SubmitInspectionResult;
use App\Http\Requests\StoreInspectionResultRequest;
use App\Http\Resources\SessionProgressResource;
use App\Models\InspectionSession;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InspectionResultController extends Controller
{
    public function __construct(private readonly CurrentSessionSample $currentSample) {}

    /**
     * Store one outcome for one sample: a `good`/`defect` verdict or a
     * timeout. A repeated identical payload returns the stored row with 200
     * so the offline queue can retry safely; a conflicting one is rejected.
     */
    public function store(
        StoreInspectionResultRequest $request,
        InspectionSession $session,
        SubmitInspectionResult $submitResult,
    ): JsonResponse {
        $participant = $session->participants()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $alreadyStored = $participant->results()
            ->where('session_sample_id', $request->integer('session_sample_id'))
            ->exists();

        $result = $submitResult->handle($participant, $request->validated());
        $result->loadMissing('sessionSample');

        $session->refresh();
        $participant->refresh()->setRelation('session', $session);

        $current = $this->currentSample->handle($participant);

        $resource = new SessionProgressResource(
            $participant,
            $current['sample'],
            $current['total'],
            $participant->results()->count(),
            $result,
            ! $alreadyStored,
        );

        return $resource
            ->response()
            ->setStatusCode($alreadyStored ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}

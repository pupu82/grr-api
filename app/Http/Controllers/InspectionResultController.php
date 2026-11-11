<?php

namespace App\Http\Controllers;

use App\Actions\Sessions\CurrentSessionSample;
use App\Actions\Sessions\SubmitInspectionResult;
use App\Enums\Role;
use App\Http\Requests\StoreInspectionResultRequest;
use App\Http\Resources\SessionProgressResource;
use App\Http\Resources\SessionResultsResource;
use App\Models\InspectionSession;
use App\Models\SessionSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InspectionResultController extends Controller
{
    public function __construct(private readonly CurrentSessionSample $currentSample) {}

    /**
     * List every stored outcome of a session, grouped by participant. Admins
     * and managers see all participants; an inspector sees only their own row.
     *
     * A `timed_out` result has no verdict, so it is missing data and never
     * `good` or `defect`. The answer key is only exposed to admins and
     * managers, through the sample snapshot.
     */
    public function index(Request $request, InspectionSession $session): SessionResultsResource
    {
        $user = $request->user();

        $session->load([
            'sessionSamples' => fn ($query) => $query->orderBy('position')->with('sample'),
        ]);

        $participants = $session->participants()
            ->with([
                // A deleted inspector still appears in historical results.
                'user' => fn ($query) => $query->withTrashed(),
                'results' => fn ($query) => $query
                    ->with('sessionSample')
                    // Order by the sample position, not by insertion order.
                    ->orderBy(
                        SessionSample::query()
                            ->select('position')
                            ->whereColumn('session_samples.id', 'inspection_results.session_sample_id'),
                    ),
            ])
            ->withCount(['results', 'timedOutResults'])
            ->when(
                $user->role === Role::Inspector,
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->orderBy('id')
            ->get();

        $session->setRelation('participants', $participants)
            ->loadCount(['sessionSamples', 'participants']);

        return new SessionResultsResource($session);
    }

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

<?php

namespace App\Http\Controllers;

use App\Actions\Sessions\CloseInspectionSession;
use App\Actions\Sessions\CreateInspectionSession;
use App\Actions\Sessions\CurrentSessionSample;
use App\Actions\Sessions\DeleteInspectionSession;
use App\Actions\Sessions\StartInspectionSession;
use App\Actions\Sessions\StartParticipantRun;
use App\Enums\Role;
use App\Http\Requests\StoreInspectionSessionRequest;
use App\Http\Resources\InspectionSessionResource;
use App\Http\Resources\SessionProgressResource;
use App\Models\InspectionResult;
use App\Models\InspectionSession;
use App\Models\SessionParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InspectionSessionController extends Controller
{
    public function __construct(private readonly CurrentSessionSample $currentSample) {}

    /**
     * List sessions. Admins and managers see all; an inspector sees only the
     * sessions they were registered for.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $sessions = InspectionSession::query()
            ->with(['plant', 'team', 'partType'])
            ->withCount(['sessionSamples', 'participants'])
            ->when(
                $user->role === Role::Inspector,
                fn ($query) => $query->whereHas(
                    'participants',
                    fn ($query) => $query->where('user_id', $user->id),
                ),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('team_id'),
                fn ($query) => $query->where('team_id', $request->integer('team_id')),
            )
            ->when(
                $request->filled('part_type_id'),
                fn ($query) => $query->where('part_type_id', $request->integer('part_type_id')),
            )
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate(50);

        return InspectionSessionResource::collection($sessions);
    }

    /**
     * Create a draft session with a snapshot of the selected samples and the
     * selected inspectors.
     */
    public function store(StoreInspectionSessionRequest $request, CreateInspectionSession $createSession): JsonResponse
    {
        $session = $createSession->handle($request->user(), $request->validated());

        return (new InspectionSessionResource($this->detail($session)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show one session with its samples (answer keys only for admin/manager)
     * and participants.
     */
    public function show(InspectionSession $session): InspectionSessionResource
    {
        return new InspectionSessionResource($this->detail($session));
    }

    /**
     * Start the real inspection. Samples, participants and the time limit are
     * frozen from this point on.
     */
    public function start(InspectionSession $session, StartInspectionSession $startSession): InspectionSessionResource
    {
        return new InspectionSessionResource($this->detail($startSession->handle($session)));
    }

    /**
     * Start this inspector's run. Their stopwatch starts on the client; the
     * server only records when the run began. Starting twice keeps the first
     * time, so a retry is harmless.
     */
    public function startRun(
        Request $request,
        InspectionSession $session,
        StartParticipantRun $startRun,
    ): SessionProgressResource {
        $participant = $this->participant($session, $request);

        $startRun->handle($participant);

        $session->refresh();
        $participant->setRelation('session', $session);

        return $this->progress($participant);
    }

    /**
     * The sample this inspector must answer next. Answers are strictly
     * sequential, so this is also the only sample they may submit.
     */
    public function currentSample(Request $request, InspectionSession $session): SessionProgressResource
    {
        $participant = $this->participant($session, $request);
        $participant->setRelation('session', $session);

        return $this->progress($participant);
    }

    /**
     * Force-close an in-progress session. Unfinished participants stay
     * unfinished and are not evaluated; no verdict or timeout is invented.
     */
    public function close(InspectionSession $session, CloseInspectionSession $closeSession): InspectionSessionResource
    {
        return new InspectionSessionResource($this->detail($closeSession->handle($session)));
    }

    /**
     * Delete a draft session.
     */
    public function destroy(InspectionSession $session, DeleteInspectionSession $deleteSession): Response
    {
        $deleteSession->handle($session);

        return response()->noContent();
    }

    /**
     * Resolve the inspector's own participant row. The policy already checked
     * membership, so a miss here only happens for a stale request.
     */
    private function participant(InspectionSession $session, Request $request): SessionParticipant
    {
        return $session->participants()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function progress(
        SessionParticipant $participant,
        ?InspectionResult $result = null,
        ?bool $resultCreated = null,
    ): SessionProgressResource {
        $current = $this->currentSample->handle($participant);

        return new SessionProgressResource(
            $participant,
            $current['sample'],
            $current['total'],
            $participant->results()->count(),
            $result,
            $resultCreated,
        );
    }

    /**
     * Load everything the resource needs without N+1 queries.
     */
    private function detail(InspectionSession $session): InspectionSession
    {
        return $session
            ->load([
                'plant',
                'team',
                'partType',
                'sessionSamples' => fn ($query) => $query->orderBy('position')->with('sample'),
                'participants' => fn ($query) => $query->with('user')->withCount('results'),
            ])
            ->loadCount(['sessionSamples', 'participants']);
    }
}

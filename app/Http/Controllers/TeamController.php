<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\CreateTeam;
use App\Actions\MasterData\DeleteTeam;
use App\Actions\MasterData\UpdateTeam;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $teams = Team::query()
            ->with('plant')
            ->when($request->filled('plant_id'), fn ($query) => $query
                ->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('search'), fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate(50);

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request, CreateTeam $createTeam): JsonResponse
    {
        $team = $createTeam->handle($request->validated());

        return (new TeamResource($team->load('plant')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Team $team): TeamResource
    {
        return new TeamResource($team->loadMissing('plant'));
    }

    public function update(UpdateTeamRequest $request, Team $team, UpdateTeam $updateTeam): TeamResource
    {
        return new TeamResource($updateTeam->handle($team, $request->validated())->load('plant'));
    }

    public function destroy(Team $team, DeleteTeam $deleteTeam): Response
    {
        $deleteTeam->handle($team);

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\CreatePartType;
use App\Actions\MasterData\DeletePartType;
use App\Actions\MasterData\UpdatePartType;
use App\Http\Requests\StorePartTypeRequest;
use App\Http\Requests\UpdatePartTypeRequest;
use App\Http\Resources\PartTypeResource;
use App\Models\PartType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PartTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $partTypes = PartType::query()
            ->withCount('samples')
            ->when($request->filled('search'), fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate(50);

        return PartTypeResource::collection($partTypes);
    }

    public function store(StorePartTypeRequest $request, CreatePartType $createPartType): JsonResponse
    {
        $partType = $createPartType->handle($request->validated());

        return (new PartTypeResource($partType))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PartType $partType): PartTypeResource
    {
        return new PartTypeResource($partType->loadCount('samples'));
    }

    public function update(
        UpdatePartTypeRequest $request,
        PartType $partType,
        UpdatePartType $updatePartType,
    ): PartTypeResource {
        return new PartTypeResource($updatePartType->handle($partType, $request->validated()));
    }

    public function destroy(PartType $partType, DeletePartType $deletePartType): Response
    {
        $deletePartType->handle($partType);

        return response()->noContent();
    }
}

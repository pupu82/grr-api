<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\CreatePartType;
use App\Actions\MasterData\DeletePartType;
use App\Actions\MasterData\UpdatePartType;
use App\Http\Requests\IndexPartTypeRequest;
use App\Http\Requests\StorePartTypeRequest;
use App\Http\Requests\UpdatePartTypeRequest;
use App\Http\Resources\PartTypeResource;
use App\Models\PartType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PartTypeController extends Controller
{
    public function index(IndexPartTypeRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $partTypes = PartType::query()
            ->withCount('samples')
            ->when(isset($filters['search']), function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    foreach ($filters['search'] as $term) {
                        $query->orWhere('name', 'like', '%'.$term.'%');
                    }
                });
            })
            ->when(isset($filters['name']), fn ($query) => $query->whereIn('name', $filters['name']))
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

<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\CreatePlant;
use App\Actions\MasterData\DeletePlant;
use App\Actions\MasterData\UpdatePlant;
use App\Http\Requests\IndexPlantRequest;
use App\Http\Requests\StorePlantRequest;
use App\Http\Requests\UpdatePlantRequest;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PlantController extends Controller
{
    public function index(IndexPlantRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $plants = Plant::query()
            ->when(isset($filters['search']), function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    foreach ($filters['search'] as $term) {
                        $search = '%'.$term.'%';

                        $query->orWhere(fn ($query) => $query
                            ->where('code', 'like', $search)
                            ->orWhere('name', 'like', $search));
                    }
                });
            })
            ->when(isset($filters['code']), fn ($query) => $query->whereIn('code', $filters['code']))
            ->when(array_key_exists('is_active', $filters), fn ($query) => $query
                ->where('is_active', $request->boolean('is_active')))
            ->orderBy('code')
            ->paginate(50);

        return PlantResource::collection($plants);
    }

    public function store(StorePlantRequest $request, CreatePlant $createPlant): JsonResponse
    {
        $plant = $createPlant->handle($request->validated());

        return (new PlantResource($plant))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Plant $plant): PlantResource
    {
        return new PlantResource($plant);
    }

    public function update(UpdatePlantRequest $request, Plant $plant, UpdatePlant $updatePlant): PlantResource
    {
        return new PlantResource($updatePlant->handle($plant, $request->validated()));
    }

    public function destroy(Plant $plant, DeletePlant $deletePlant): Response
    {
        $deletePlant->handle($plant);

        return response()->noContent();
    }
}

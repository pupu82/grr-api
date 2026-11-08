<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\CreatePlant;
use App\Actions\MasterData\DeletePlant;
use App\Actions\MasterData\UpdatePlant;
use App\Http\Requests\StorePlantRequest;
use App\Http\Requests\UpdatePlantRequest;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PlantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $plants = Plant::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';

                $query->where(fn ($query) => $query
                    ->where('code', 'like', $search)
                    ->orWhere('name', 'like', $search));
            })
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

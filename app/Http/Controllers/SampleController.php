<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\CreateSample;
use App\Actions\MasterData\DeleteSample;
use App\Actions\MasterData\UpdateSample;
use App\Http\Requests\StoreSampleRequest;
use App\Http\Requests\UpdateSampleRequest;
use App\Http\Resources\SampleResource;
use App\Models\Sample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SampleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $samples = Sample::query()
            ->with('partType')
            ->when($request->filled('part_type_id'), fn ($query) => $query
                ->where('part_type_id', $request->integer('part_type_id')))
            ->when($request->filled('search'), fn ($query) => $query
                ->where('code', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('code')
            ->paginate(50);

        return SampleResource::collection($samples);
    }

    public function store(StoreSampleRequest $request, CreateSample $createSample): JsonResponse
    {
        $sample = $createSample->handle($request->validated());

        return (new SampleResource($sample->load('partType')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Sample $sample): SampleResource
    {
        return new SampleResource($sample->loadMissing('partType'));
    }

    public function update(
        UpdateSampleRequest $request,
        Sample $sample,
        UpdateSample $updateSample,
    ): SampleResource {
        $sample = $updateSample->handle($sample, $request->validated(), $request->user());

        return new SampleResource($sample->load('partType'));
    }

    public function destroy(Sample $sample, DeleteSample $deleteSample): Response
    {
        $deleteSample->handle($sample);

        return response()->noContent();
    }
}

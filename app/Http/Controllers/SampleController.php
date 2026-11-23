<?php

namespace App\Http\Controllers;

use App\Actions\MasterData\CreateSample;
use App\Actions\MasterData\DeleteSample;
use App\Actions\MasterData\UpdateSample;
use App\Http\Requests\IndexSampleRequest;
use App\Http\Requests\StoreSampleRequest;
use App\Http\Requests\UpdateSampleRequest;
use App\Http\Resources\SampleResource;
use App\Models\Sample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SampleController extends Controller
{
    public function index(IndexSampleRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $samples = Sample::query()
            ->with('partType')
            ->when(isset($filters['part_type_id']), fn ($query) => $query
                ->whereIn('part_type_id', $filters['part_type_id']))
            ->when(isset($filters['search']), function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    foreach ($filters['search'] as $term) {
                        $query->orWhere('code', 'like', '%'.$term.'%');
                    }
                });
            })
            ->when(isset($filters['code']), fn ($query) => $query->whereIn('code', $filters['code']))
            ->when(isset($filters['answer_key']), fn ($query) => $query
                ->whereIn('answer_key', $filters['answer_key']))
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

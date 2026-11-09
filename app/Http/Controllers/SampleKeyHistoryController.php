<?php

namespace App\Http\Controllers;

use App\Http\Resources\SampleKeyHistoryResource;
use App\Models\Sample;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SampleKeyHistoryController extends Controller
{
    /**
     * List the answer-key history of a sample, newest first.
     */
    public function index(Sample $sample): AnonymousResourceCollection
    {
        $history = $sample->keyHistories()
            ->with(['changer' => fn ($query) => $query->withTrashed()])
            ->orderByDesc('changed_at')
            ->paginate(50);

        return SampleKeyHistoryResource::collection($history);
    }
}

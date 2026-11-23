<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ActivityLogController extends Controller
{
    /**
     * List administrative activity, newest first. Filters accept one value or
     * several; values inside one filter are OR-ed, different filters are AND-ed.
     */
    public function index(IndexActivityLogRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $logs = ActivityLog::query()
            ->with(['actor' => fn ($query) => $query->withTrashed()])
            ->when(isset($filters['action']), fn ($query) => $query->whereIn('action', $filters['action']))
            ->when(isset($filters['actor_id']), function ($query) use ($filters): void {
                // The public identifier is a uuid; resolve it to the internal id.
                $actorIds = User::query()
                    ->withTrashed()
                    ->whereIn('uuid', $filters['actor_id'])
                    ->pluck('id');

                $query->whereIn('actor_id', $actorIds);
            })
            ->when(isset($filters['subject_type']), fn ($query) => $query->whereIn('subject_type', $filters['subject_type']))
            ->when(isset($filters['subject_id']), fn ($query) => $query->whereIn('subject_id', $filters['subject_id']))
            ->when(isset($filters['date_from']), fn ($query) => $query
                ->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay()))
            ->when(isset($filters['date_to']), fn ($query) => $query
                ->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);

        return ActivityLogResource::collection($logs);
    }
}

<?php

namespace App\Actions\Sessions;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInspectionSession
{
    public function __construct(
        private LogActivity $logActivity,
        private SnapshotSessionSamples $snapshotSamples,
    ) {}

    /**
     * Change a draft before it is started. Replacing the samples re-copies the
     * current answer keys and positions, because no inspection has happened yet
     * and the snapshot has no history to preserve.
     *
     * @param  array{
     *     session_date?: string,
     *     response_time_limit_ms?: int,
     *     sample_ids?: array<int, int>,
     *     participant_ids?: array<int, string>
     * }  $attributes
     */
    public function handle(InspectionSession $session, array $attributes, User $actor): InspectionSession
    {
        if ($session->status !== SessionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft session can be updated.',
            ]);
        }

        DB::transaction(function () use ($session, $attributes, $actor): void {
            $scalars = array_intersect_key($attributes, array_flip([
                'session_date',
                'response_time_limit_ms',
            ]));

            if ($scalars !== []) {
                $session->fill($scalars)->save();
            }

            if (array_key_exists('sample_ids', $attributes)) {
                $this->snapshotSamples->handle($session, $attributes['sample_ids']);
            }

            if (array_key_exists('participant_ids', $attributes)) {
                $this->replaceParticipants($session, $attributes['participant_ids']);
            }

            $this->logActivity->handle(
                ActivityAction::SessionUpdated,
                $actor,
                'session',
                $session->uuid,
                ['fields' => array_keys($attributes)],
            );
        });

        return $session->refresh();
    }

    /**
     * @param  array<int, string>  $participantUuids
     */
    private function replaceParticipants(InspectionSession $session, array $participantUuids): void
    {
        $session->participants()->delete();

        $users = User::query()
            ->whereIn('uuid', $participantUuids)
            ->get()
            ->keyBy('uuid');

        foreach ($participantUuids as $uuid) {
            $session->participants()->create([
                'user_id' => $users->get($uuid)->id,
            ]);
        }
    }
}

<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use App\Models\Sample;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateInspectionSession
{
    private const MAX_TOKEN_ATTEMPTS = 5;

    public function __construct(private GenerateSessionToken $generateToken) {}

    /**
     * Create a draft session, snapshot the selected samples and register the
     * selected inspectors.
     *
     * @param  array{
     *     team_id: int,
     *     part_type_id: int,
     *     session_date: string,
     *     response_time_limit_ms?: int,
     *     sample_ids: array<int, int>,
     *     participant_ids: array<int, string>
     * }  $attributes
     */
    public function handle(User $actor, array $attributes): InspectionSession
    {
        for ($attempt = 1; $attempt <= self::MAX_TOKEN_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(fn (): InspectionSession => $this->create($actor, $attributes));
            } catch (UniqueConstraintViolationException $exception) {
                // The only unique value generated here is the token. Try the
                // next number; anything else is a programming error.
                if (! str_contains($exception->getMessage(), 'token')) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Could not generate a unique session token.');
    }

    /**
     * @param  array{
     *     team_id: int,
     *     part_type_id: int,
     *     session_date: string,
     *     response_time_limit_ms?: int,
     *     sample_ids: array<int, int>,
     *     participant_ids: array<int, string>
     * }  $attributes
     */
    private function create(User $actor, array $attributes): InspectionSession
    {
        $team = Team::query()->findOrFail($attributes['team_id']);

        $samples = Sample::query()
            ->whereIn('id', $attributes['sample_ids'])
            ->get()
            ->keyBy('id');

        $participants = User::query()
            ->whereIn('uuid', $attributes['participant_ids'])
            ->get()
            ->keyBy('uuid');

        $session = new InspectionSession;
        $session->fill([
            'token' => $this->generateToken->handle(Carbon::parse($attributes['session_date'])->year),
            'plant_id' => $team->plant_id,
            'team_id' => $team->id,
            'part_type_id' => $attributes['part_type_id'],
            'session_date' => $attributes['session_date'],
            'response_time_limit_ms' => $attributes['response_time_limit_ms']
                ?? config('grr.inspection.response_time_limit_ms'),
        ]);
        $session->status = SessionStatus::Draft;
        $session->created_by = $actor->id;
        $session->save();

        // The request order is the inspection order: position 1 is the first
        // sample the inspector sees.
        foreach (array_values($attributes['sample_ids']) as $index => $sampleId) {
            $sample = $samples->get($sampleId);

            $session->sessionSamples()->create([
                'sample_id' => $sample->id,
                'answer_key' => $sample->answer_key,
                'position' => $index + 1,
            ]);
        }

        foreach ($attributes['participant_ids'] as $uuid) {
            $session->participants()->create([
                'user_id' => $participants->get($uuid)->id,
            ]);
        }

        return $session;
    }
}

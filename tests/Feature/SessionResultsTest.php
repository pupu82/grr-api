<?php

use App\Enums\Verdict;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->plant = Plant::factory()->create();
    $this->team = Team::factory()->for($this->plant)->create();
    $this->inspector = User::factory()->inspector()->forTeam($this->team)->create();
    $this->secondInspector = User::factory()->inspector()->forTeam($this->team)->create();
});

/**
 * Build a completed session with three ordered samples, two participants and
 * one timed-out result, so the payload has every outcome kind.
 *
 * @return array{0: InspectionSession, 1: array<int, int>}
 */
function sessionResultsFixture(object $test): array
{
    $partType = PartType::factory()->create();

    $samples = collect([
        Sample::factory()->for($partType)->good()->create(),
        Sample::factory()->for($partType)->good()->create(),
        Sample::factory()->for($partType)->defect()->create(),
    ]);

    $session = InspectionSession::factory()->forTeam($test->team)->completed()->create([
        'part_type_id' => $partType->id,
        'response_time_limit_ms' => 90000,
    ]);

    $sessionSampleIds = [];

    foreach ($samples as $index => $sample) {
        $sessionSampleIds[] = $session->sessionSamples()->create([
            'sample_id' => $sample->id,
            'answer_key' => $sample->answer_key,
            'position' => $index + 1,
        ])->id;
    }

    $completedAt = now()->subMinutes(10);

    // Timestamps are not mass-assignable on a participant.
    $first = $session->participants()->create(['user_id' => $test->inspector->id]);
    $first->forceFill([
        'started_at' => $completedAt->copy()->subMinutes(5),
        'completed_at' => $completedAt,
    ])->save();

    $second = $session->participants()->create(['user_id' => $test->secondInspector->id]);
    $second->forceFill([
        'started_at' => $completedAt->copy()->subMinutes(5),
        'completed_at' => $completedAt,
    ])->save();

    // The first inspector answers the first sample, times out on the second and
    // answers the last one. The second inspector answers everything.
    $first->results()->create([
        'session_sample_id' => $sessionSampleIds[0],
        'outcome' => 'answered',
        'verdict' => Verdict::Good,
        'response_time_ms' => 1500,
        'answered_at' => '2026-11-12T08:00:00+00:00',
    ]);
    $first->results()->create([
        'session_sample_id' => $sessionSampleIds[1],
        'outcome' => 'timed_out',
        'verdict' => null,
        'response_time_ms' => 90000,
        'answered_at' => '2026-11-12T08:02:00+00:00',
    ]);
    $first->results()->create([
        'session_sample_id' => $sessionSampleIds[2],
        'outcome' => 'answered',
        'verdict' => Verdict::Defect,
        'response_time_ms' => 2500,
        'answered_at' => '2026-11-12T08:05:00+00:00',
    ]);

    foreach ($sessionSampleIds as $index => $sessionSampleId) {
        $second->results()->create([
            'session_sample_id' => $sessionSampleId,
            'outcome' => 'answered',
            'verdict' => $index === 2 ? Verdict::Defect : Verdict::Good,
            'response_time_ms' => 2000,
            'answered_at' => '2026-11-12T08:00:00+00:00',
        ]);
    }

    return [$session, $sessionSampleIds];
}

test('an admin sees every result of a session with the sample answer keys', function () {
    [$session] = sessionResultsFixture($this);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertOk()
        ->assertJsonPath('data.session.id', $session->uuid)
        ->assertJsonPath('data.session.status', 'completed')
        ->assertJsonPath('data.session.response_time_limit_ms', 90000)
        ->assertJsonPath('data.session.samples_count', 3)
        ->assertJsonPath('data.session.participants_count', 2)
        ->assertJsonCount(3, 'data.samples')
        ->assertJsonCount(2, 'data.participants')
        ->assertJsonPath('data.samples.0.position', 1)
        ->assertJsonPath('data.samples.0.answer_key', 'good')
        ->assertJsonPath('data.samples.2.answer_key', 'defect')
        ->assertJsonPath('data.participants.0.user.id', $this->inspector->uuid)
        ->assertJsonPath('data.participants.0.results_count', 3)
        ->assertJsonPath('data.participants.0.timed_out_count', 1)
        ->assertJsonPath('data.participants.0.is_complete', true)
        ->assertJsonPath('data.participants.0.results.0.position', 1)
        ->assertJsonPath('data.participants.0.results.0.verdict', 'good')
        ->assertJsonPath('data.participants.0.results.1.position', 2)
        ->assertJsonPath('data.participants.0.results.1.outcome', 'timed_out')
        ->assertJsonPath('data.participants.0.results.1.verdict', null)
        ->assertJsonPath('data.participants.0.results.2.position', 3)
        ->assertJsonPath('data.participants.1.user.id', $this->secondInspector->uuid)
        ->assertJsonPath('data.participants.1.timed_out_count', 0);
});

test('a manager reads every result but changes nothing', function () {
    [$session] = sessionResultsFixture($this);

    Sanctum::actingAs($this->manager);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertOk()
        ->assertJsonCount(2, 'data.participants')
        ->assertJsonPath('data.samples.0.answer_key', 'good');

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/close')
        ->assertForbidden();
});

test('an inspector sees only their own results and never an answer key', function () {
    [$session] = sessionResultsFixture($this);

    Sanctum::actingAs($this->inspector);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertOk()
        ->assertJsonCount(1, 'data.participants')
        ->assertJsonPath('data.participants.0.user.id', $this->inspector->uuid)
        ->assertJsonPath('data.participants.0.results_count', 3)
        ->assertJsonPath('data.participants.0.timed_out_count', 1)
        ->assertJsonMissingPath('data.samples.0.answer_key')
        ->assertJsonMissingPath('data.participants.1')
        ->assertJsonMissing(['user' => ['id' => $this->secondInspector->uuid]]);
});

test('an inspector who does not participate cannot read the results', function () {
    [$session] = sessionResultsFixture($this);

    $outsider = User::factory()->inspector()->forTeam($this->team)->create();

    Sanctum::actingAs($outsider);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

test('the results endpoint requires authentication', function () {
    [$session] = sessionResultsFixture($this);

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

test('a draft session lists its participants with no results yet', function () {
    $session = InspectionSession::factory()->forTeam($this->team)->create();
    $session->participants()->create(['user_id' => $this->inspector->id]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertOk()
        ->assertJsonPath('data.session.status', 'draft')
        ->assertJsonPath('data.participants.0.results_count', 0)
        ->assertJsonPath('data.participants.0.results', [])
        ->assertJsonPath('data.participants.0.is_complete', false);
});

test('a deleted inspector still appears in historical results', function () {
    [$session] = sessionResultsFixture($this);

    $this->inspector->delete();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertOk()
        ->assertJsonPath('data.participants.0.user.id', $this->inspector->uuid)
        ->assertJsonPath('data.participants.0.user.name', $this->inspector->name)
        ->assertJsonPath('data.participants.0.results_count', 3);
});

test('results are ordered by sample position and scoped to the session', function () {
    [$session, $sessionSampleIds] = sessionResultsFixture($this);
    [, $otherSampleIds] = sessionResultsFixture($this);

    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/v1/sessions/'.$session->uuid.'/results')
        ->assertOk()
        ->assertJsonCount(3, 'data.participants.0.results');

    $returnedSampleIds = collect($response->json('data.participants.0.results'))
        ->pluck('session_sample_id')
        ->all();

    expect($returnedSampleIds)->toBe($sessionSampleIds)
        ->and(array_intersect($returnedSampleIds, $otherSampleIds))->toBe([]);
});

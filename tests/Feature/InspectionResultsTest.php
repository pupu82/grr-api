<?php

use App\Enums\SessionStatus;
use App\Models\InspectionResult;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\SessionSample;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->plant = Plant::factory()->create();
    $this->team = Team::factory()->for($this->plant)->create();
    $this->inspector = User::factory()->inspector()->forTeam($this->team)->create();

    Sanctum::actingAs($this->admin);
});

/**
 * Build an in-progress session with ordered samples and one participant.
 *
 * @return array{0: InspectionSession, 1: Collection<int, SessionSample>}
 */
function inspectionRunSession(object $test, int $sampleCount = 3): array
{
    $partType = PartType::factory()->create();
    $samples = Sample::factory()->count($sampleCount)->for($partType)->create();

    $session = InspectionSession::factory()->forTeam($test->team)->inProgress()->create([
        'part_type_id' => $partType->id,
        'response_time_limit_ms' => 90000,
    ]);

    foreach ($samples as $index => $sample) {
        $session->sessionSamples()->create([
            'sample_id' => $sample->id,
            'answer_key' => $sample->answer_key,
            'position' => $index + 1,
        ]);
    }

    $session->participants()->create(['user_id' => $test->inspector->id]);

    return [$session, $session->sessionSamples()->with('sample')->orderBy('position')->get()];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inspectionAnswer(object $sample, array $overrides = []): array
{
    return array_merge([
        'session_sample_id' => $sample->id,
        'outcome' => 'answered',
        'verdict' => 'good',
        'response_time_ms' => 12345,
        'answered_at' => '2026-11-12T08:00:00+00:00',
    ], $overrides);
}

test('an inspector starts their run and receives the first sample without the answer key', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')
        ->assertOk()
        ->assertJsonPath('data.session.id', $session->uuid)
        ->assertJsonPath('data.session.status', 'in_progress')
        ->assertJsonPath('data.session.response_time_limit_ms', 90000)
        ->assertJsonPath('data.participant.answered_count', 0)
        ->assertJsonPath('data.participant.samples_count', 3)
        ->assertJsonPath('data.participant.is_complete', false)
        ->assertJsonPath('data.current_sample.id', $samples[0]->id)
        ->assertJsonPath('data.current_sample.sample_id', $samples[0]->sample_id)
        ->assertJsonPath('data.current_sample.code', $samples[0]->sample->code)
        ->assertJsonPath('data.current_sample.position', 1)
        ->assertJsonPath('data.result', null)
        ->assertJsonPath('data.result_created', null)
        ->assertJsonMissingPath('data.current_sample.answer_key');

    $startedAt = $session->participants()->sole()->started_at;

    expect($startedAt)->not->toBeNull();

    $this->travel(5)->minutes();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')
        ->assertOk()
        ->assertJsonPath('data.current_sample.position', 1);

    expect($session->participants()->sole()->started_at->equalTo($startedAt))->toBeTrue();
});

test('a run cannot be started before the admin starts the session', function () {
    $session = InspectionSession::factory()->forTeam($this->team)->create();
    $session->participants()->create(['user_id' => $this->inspector->id]);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('status');
});

test('an inspector cannot answer before starting their run', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('started_at');

    expect(InspectionResult::query()->count())->toBe(0);
});

test('answers advance one sample at a time until the participant and session finish', function () {
    [$session, $samples] = inspectionRunSession($this, 2);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0], [
        'verdict' => 'defect',
        'response_time_ms' => 1500,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.result.session_sample_id', $samples[0]->id)
        ->assertJsonPath('data.result.position', 1)
        ->assertJsonPath('data.result.outcome', 'answered')
        ->assertJsonPath('data.result.verdict', 'defect')
        ->assertJsonPath('data.result.response_time_ms', 1500)
        ->assertJsonPath('data.result.answered_at', '2026-11-12T08:00:00+00:00')
        ->assertJsonPath('data.result_created', true)
        ->assertJsonPath('data.participant.answered_count', 1)
        ->assertJsonPath('data.participant.is_complete', false)
        ->assertJsonPath('data.current_sample.id', $samples[1]->id)
        ->assertJsonMissingPath('data.current_sample.answer_key');

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[1]))
        ->assertCreated()
        ->assertJsonPath('data.participant.answered_count', 2)
        ->assertJsonPath('data.participant.is_complete', true)
        ->assertJsonPath('data.session.status', 'completed')
        ->assertJsonPath('data.current_sample', null);

    $session->refresh();

    expect($session->status)->toBe(SessionStatus::Completed)
        ->and($session->completed_at)->not->toBeNull()
        ->and($session->participants()->sole()->completed_at)->not->toBeNull();
});

test('the current sample endpoint reports the next unanswered sample', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/current-sample')
        ->assertOk()
        ->assertJsonPath('data.participant.started_at', null)
        ->assertJsonPath('data.current_sample.position', 1)
        ->assertJsonMissingPath('data.current_sample.answer_key');

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0]))->assertCreated();

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/current-sample')
        ->assertOk()
        ->assertJsonPath('data.participant.answered_count', 1)
        ->assertJsonPath('data.current_sample.position', 2);
});

test('answers must follow the sample position', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[1]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('session_sample_id');

    expect(InspectionResult::query()->count())->toBe(0);
});

test('a sample from another session is rejected', function () {
    [$session, $samples] = inspectionRunSession($this);
    [, $otherSamples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($otherSamples[0]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('session_sample_id');
});

test('an answered sample above the session time limit is rejected', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0], [
        'response_time_ms' => 90001,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('response_time_ms');

    expect(InspectionResult::query()->count())->toBe(0);
});

test('a timed out sample stores the elapsed time without a verdict', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0], [
        'outcome' => 'timed_out',
        'verdict' => null,
        'response_time_ms' => 90000,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.result.outcome', 'timed_out')
        ->assertJsonPath('data.result.verdict', null)
        ->assertJsonPath('data.result.response_time_ms', 90000)
        ->assertJsonPath('data.current_sample.position', 2);

    $result = InspectionResult::query()->sole();

    expect($result->verdict)->toBeNull()
        ->and($result->outcome->value)->toBe('timed_out');
});

test('the answer payload rejects inconsistent outcome and verdict', function (string $outcome, mixed $verdict, ?string $error) {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $response = $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0], [
        'outcome' => $outcome,
        'verdict' => $verdict,
    ]));

    $response->assertStatus(422)->assertJsonPath('code', 'validation_failed');

    if ($error !== null) {
        $response->assertJsonValidationErrors($error);
    }

    expect(InspectionResult::query()->count())->toBe(0);
})->with([
    ['answered', null, 'verdict'],
    ['timed_out', 'good', 'verdict'],
    ['unknown', 'good', 'outcome'],
]);

test('a repeated identical answer is idempotent', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $payload = inspectionAnswer($samples[0], ['response_time_ms' => 4321]);

    $first = $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', $payload)
        ->assertCreated()
        ->assertJsonPath('data.result_created', true);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', $payload)
        ->assertOk()
        ->assertJsonPath('data.result_created', false)
        ->assertJsonPath('data.result.id', $first->json('data.result.id'))
        ->assertJsonPath('data.participant.answered_count', 1);

    expect(InspectionResult::query()->count())->toBe(1);
});

test('a conflicting repeat of an answered sample is rejected', function () {
    [$session, $samples] = inspectionRunSession($this);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0], [
        'verdict' => 'good',
    ]))->assertCreated();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0], [
        'verdict' => 'defect',
    ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('session_sample_id');

    expect(InspectionResult::query()->count())->toBe(1);
});

test('an offline retry still succeeds after the session has completed', function () {
    [$session, $samples] = inspectionRunSession($this, 1);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();

    $payload = inspectionAnswer($samples[0]);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', $payload)->assertCreated();

    $session->refresh();
    expect($session->status)->toBe(SessionStatus::Completed);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', $payload)
        ->assertOk()
        ->assertJsonPath('data.result_created', false)
        ->assertJsonPath('data.session.status', 'completed')
        ->assertJsonPath('data.participant.is_complete', true);

    expect(InspectionResult::query()->count())->toBe(1);
});

test('the session completes only when every participant finishes', function () {
    [$session, $samples] = inspectionRunSession($this, 2);
    $second = User::factory()->inspector()->forTeam($this->team)->create();
    $session->participants()->create(['user_id' => $second->id]);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0]))->assertCreated();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[1]))
        ->assertCreated()
        ->assertJsonPath('data.session.status', 'in_progress');

    $session->refresh();
    expect($session->status)->toBe(SessionStatus::InProgress);

    Sanctum::actingAs($second);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0]))->assertCreated();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[1]))
        ->assertCreated()
        ->assertJsonPath('data.session.status', 'completed');

    $session->refresh();
    expect($session->status)->toBe(SessionStatus::Completed)
        ->and($session->participants()->whereNull('completed_at')->count())->toBe(0);
});

test('an admin can force close a session and unfinished participants stay unfinished', function () {
    [$session, $samples] = inspectionRunSession($this, 2);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start-run')->assertOk();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0]))->assertCreated();

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/close')
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.participants.0.results_count', 1)
        ->assertJsonPath('data.participants.0.is_complete', false)
        ->assertJsonPath('data.participants.0.completed_at', null);

    $session->refresh();

    expect($session->status)->toBe(SessionStatus::Completed)
        ->and($session->participants()->sole()->completed_at)->toBeNull()
        ->and(InspectionResult::query()->count())->toBe(1);

    Sanctum::actingAs($this->inspector);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/current-sample')
        ->assertOk()
        ->assertJsonPath('data.session.status', 'completed')
        ->assertJsonPath('data.participant.answered_count', 1)
        ->assertJsonPath('data.participant.is_complete', false)
        ->assertJsonPath('data.current_sample', null);

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/close')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('status');
});

test('only the assigned inspector can run and answer a session', function (string $role, string $method, string $uri) {
    [$session, $samples] = inspectionRunSession($this);

    $actor = $role === 'outsider'
        ? User::factory()->inspector()->forTeam($this->team)->create()
        : User::factory()->{$role}()->create();

    Sanctum::actingAs($actor);

    $this->json(
        $method,
        str_replace('{session}', $session->uuid, $uri),
        $method === 'post' ? inspectionAnswer($samples[0]) : [],
    )
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
})->with([
    ['manager', 'post', '/api/v1/sessions/{session}/start-run'],
    ['manager', 'get', '/api/v1/sessions/{session}/current-sample'],
    ['manager', 'post', '/api/v1/sessions/{session}/results'],
    ['manager', 'post', '/api/v1/sessions/{session}/close'],
    ['admin', 'post', '/api/v1/sessions/{session}/start-run'],
    ['admin', 'post', '/api/v1/sessions/{session}/results'],
    ['outsider', 'post', '/api/v1/sessions/{session}/start-run'],
    ['outsider', 'get', '/api/v1/sessions/{session}/current-sample'],
    ['outsider', 'post', '/api/v1/sessions/{session}/results'],
    ['inspector', 'post', '/api/v1/sessions/{session}/close'],
]);

test('the answer endpoints reject unauthenticated requests', function () {
    [$session, $samples] = inspectionRunSession($this);

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/current-sample')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/results', inspectionAnswer($samples[0]))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

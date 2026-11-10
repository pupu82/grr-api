<?php

use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin);

    $this->plant = Plant::factory()->create();
    $this->team = Team::factory()->for($this->plant)->create();
    $this->partType = PartType::factory()->create();
    $this->samples = Sample::factory()->count(3)->for($this->partType)->create();
    $this->inspectors = User::factory()->inspector()->count(2)->forTeam($this->team)->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sessionPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'team_id' => $test->team->id,
        'part_type_id' => $test->partType->id,
        'session_date' => '2026-11-12',
        'sample_ids' => $test->samples->pluck('id')->all(),
        'participant_ids' => $test->inspectors->pluck('uuid')->all(),
    ], $overrides);
}

test('an admin creates a draft session with a sample snapshot and participants', function () {
    $ordered = [$this->samples[2], $this->samples[0], $this->samples[1]];

    $this->postJson('/api/v1/sessions', sessionPayload($this, [
        'sample_ids' => collect($ordered)->pluck('id')->all(),
        'response_time_limit_ms' => 120000,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.plant_id', $this->plant->id)
        ->assertJsonPath('data.team_id', $this->team->id)
        ->assertJsonPath('data.part_type_id', $this->partType->id)
        ->assertJsonPath('data.session_date', '2026-11-12')
        ->assertJsonPath('data.response_time_limit_ms', 120000)
        ->assertJsonPath('data.token', 'SES-2026-0001')
        ->assertJsonPath('data.started_at', null)
        ->assertJsonPath('data.samples_count', 3)
        ->assertJsonPath('data.participants_count', 2)
        ->assertJsonPath('data.session_samples.0.sample_id', $ordered[0]->id)
        ->assertJsonPath('data.session_samples.0.position', 1)
        ->assertJsonPath('data.session_samples.0.answer_key', $ordered[0]->answer_key->value)
        ->assertJsonPath('data.session_samples.2.sample_id', $ordered[2]->id)
        ->assertJsonPath('data.session_samples.2.position', 3)
        ->assertJsonPath('data.participants.0.user.id', $this->inspectors[0]->uuid);

    $session = InspectionSession::query()->sole();

    expect($session->created_by)->toBe($this->admin->id)
        ->and($session->plant_id)->toBe($this->plant->id)
        ->and($session->status->value)->toBe('draft')
        ->and($session->response_time_limit_ms)->toBe(120000)
        ->and($session->sessionSamples()->orderBy('position')->pluck('sample_id')->all())
        ->toBe(collect($ordered)->pluck('id')->all())
        ->and($session->participants()->count())->toBe(2);
});

test('the session time limit defaults to ninety seconds', function () {
    $this->postJson('/api/v1/sessions', sessionPayload($this))
        ->assertCreated()
        ->assertJsonPath('data.response_time_limit_ms', 90000);
});

test('samples must belong to the chosen part type', function () {
    $foreign = Sample::factory()->create();

    $this->postJson('/api/v1/sessions', sessionPayload($this, [
        'sample_ids' => [$foreign->id],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('sample_ids.0');

    expect(InspectionSession::query()->count())->toBe(0);
});

test('participants must be active inspectors of the chosen team', function () {
    $otherTeam = Team::factory()->for($this->plant)->create();
    $outsider = User::factory()->inspector()->forTeam($otherTeam)->create();

    $this->postJson('/api/v1/sessions', sessionPayload($this, [
        'participant_ids' => [$outsider->uuid],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('participant_ids.0');

    $pending = User::factory()->inspector()->pending()->forTeam($this->team)->create();

    $this->postJson('/api/v1/sessions', sessionPayload($this, [
        'participant_ids' => [$pending->uuid],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('participant_ids.0');
});

test('only an admin can create, start or delete a session', function (string $role, string $method, string $uri) {
    $session = InspectionSession::factory()->forTeam($this->team)->create();
    $actor = User::factory()->{$role}()->create();
    Sanctum::actingAs($actor);

    $this->json($method, str_replace('{session}', $session->uuid, $uri))
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
})->with([
    ['manager', 'post', '/api/v1/sessions'],
    ['inspector', 'post', '/api/v1/sessions'],
    ['manager', 'post', '/api/v1/sessions/{session}/start'],
    ['manager', 'delete', '/api/v1/sessions/{session}'],
]);

test('starting a session freezes it and records the start time', function () {
    $session = InspectionSession::factory()->forTeam($this->team)->create();

    $response = $this->postJson('/api/v1/sessions/'.$session->uuid.'/start')
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    expect($response->json('data.started_at'))->not->toBeNull();

    $session->refresh();

    expect($session->status->value)->toBe('in_progress')
        ->and($session->started_at)->not->toBeNull();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('status');
});

test('an inspector only sees and opens the sessions they participate in', function () {
    $own = InspectionSession::factory()->forTeam($this->team)->create();
    $own->participants()->create(['user_id' => $this->inspectors[0]->id]);

    $other = InspectionSession::factory()->forTeam($this->team)->create();
    $other->participants()->create(['user_id' => $this->inspectors[1]->id]);

    Sanctum::actingAs($this->inspectors[0]);

    $this->getJson('/api/v1/sessions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->uuid);

    $this->getJson('/api/v1/sessions/'.$own->uuid)
        ->assertOk()
        ->assertJsonPath('data.id', $own->uuid);

    $this->getJson('/api/v1/sessions/'.$other->uuid)
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

test('an inspector never receives the snapshotted answer key', function () {
    $session = InspectionSession::factory()->forTeam($this->team)->create();
    $session->participants()->create(['user_id' => $this->inspectors[0]->id]);
    $session->sessionSamples()->create([
        'sample_id' => $this->samples[0]->id,
        'answer_key' => $this->samples[0]->answer_key,
        'position' => 1,
    ]);

    Sanctum::actingAs($this->inspectors[0]);

    $this->getJson('/api/v1/sessions/'.$session->uuid)
        ->assertOk()
        ->assertJsonPath('data.session_samples.0.id', $session->sessionSamples()->sole()->id)
        ->assertJsonMissingPath('data.session_samples.0.answer_key');
});

test('a manager sees every session but only the admin can change it', function () {
    InspectionSession::factory()->count(2)->forTeam($this->team)->create();

    $manager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);

    $this->getJson('/api/v1/sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('only a draft session can be deleted', function () {
    $draft = InspectionSession::factory()->forTeam($this->team)->create();
    $draft->sessionSamples()->create([
        'sample_id' => $this->samples[0]->id,
        'answer_key' => $this->samples[0]->answer_key,
        'position' => 1,
    ]);
    $draft->participants()->create(['user_id' => $this->inspectors[0]->id]);

    $this->deleteJson('/api/v1/sessions/'.$draft->uuid)->assertNoContent();

    expect(InspectionSession::query()->count())->toBe(0)
        ->and($draft->sessionSamples()->count())->toBe(0)
        ->and($draft->participants()->count())->toBe(0);

    $started = InspectionSession::factory()->forTeam($this->team)->inProgress()->create();

    $this->deleteJson('/api/v1/sessions/'.$started->uuid)
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed');
});

test('the session endpoints reject unauthenticated requests', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/sessions')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');

    $this->postJson('/api/v1/sessions', [])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

test('the session token increments per year', function () {
    $this->postJson('/api/v1/sessions', sessionPayload($this, ['session_date' => '2026-11-12']))
        ->assertCreated()
        ->assertJsonPath('data.token', 'SES-2026-0001');

    $this->postJson('/api/v1/sessions', sessionPayload($this, ['session_date' => '2026-12-01']))
        ->assertCreated()
        ->assertJsonPath('data.token', 'SES-2026-0002');

    $this->postJson('/api/v1/sessions', sessionPayload($this, ['session_date' => '2027-01-05']))
        ->assertCreated()
        ->assertJsonPath('data.token', 'SES-2027-0001');
});

test('a session response never exposes the internal creator id', function () {
    $this->postJson('/api/v1/sessions', sessionPayload($this))
        ->assertCreated()
        ->assertJsonMissingPath('data.created_by');
});

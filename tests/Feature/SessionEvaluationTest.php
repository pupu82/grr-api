<?php

use App\Enums\SessionStatus;
use App\Enums\Verdict;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\SessionParticipant;
use App\Models\SessionSample;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->plant = Plant::factory()->create();
    $this->team = Team::factory()->for($this->plant)->create();
    $this->inspector = User::factory()->inspector()->forTeam($this->team)->create();
});

/**
 * A completed session with one `good` and one `defect` sample.
 *
 * @return array{0: InspectionSession, 1: array<int, SessionSample>}
 */
function evaluationSession(object $test): array
{
    $partType = PartType::factory()->create();

    $session = InspectionSession::factory()->forTeam($test->team)->completed()->create([
        'part_type_id' => $partType->id,
        'response_time_limit_ms' => 90000,
    ]);

    $sessionSamples = [];

    foreach ([Verdict::Good, Verdict::Defect] as $index => $answerKey) {
        $sample = Sample::factory()->for($partType)->create(['answer_key' => $answerKey]);

        $sessionSamples[] = $session->sessionSamples()->create([
            'sample_id' => $sample->id,
            'answer_key' => $answerKey,
            'position' => $index + 1,
        ]);
    }

    return [$session, $sessionSamples];
}

/**
 * Record one outcome per entry. `null` means the inspector timed out; a shorter
 * array leaves the remaining samples unanswered and the participant unfinished.
 *
 * @param  array<int, Verdict|null>  $verdicts
 */
function evaluationParticipant(
    InspectionSession $session,
    array $sessionSamples,
    array $verdicts,
    bool $finished = true,
): SessionParticipant {
    $participant = $session->participants()->create([
        'user_id' => User::factory()->inspector()->create()->id,
    ]);

    $participant->forceFill([
        'started_at' => now()->subMinutes(10),
        'completed_at' => $finished ? now()->subMinute() : null,
    ])->save();

    foreach (array_values($verdicts) as $index => $verdict) {
        $timedOut = $verdict === null;

        $participant->results()->create([
            'session_sample_id' => $sessionSamples[$index]->id,
            'outcome' => $timedOut ? 'timed_out' : 'answered',
            'verdict' => $verdict,
            'response_time_ms' => $timedOut ? 90000 : 1500,
            'answered_at' => now(),
        ]);
    }

    return $participant;
}

test('an admin reads the computed metrics of every participant', function () {
    [$session, $samples] = evaluationSession($this);
    $correct = evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect]);
    $partial = evaluationParticipant($session, $samples, [Verdict::Defect, null]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.session.id', $session->uuid)
        ->assertJsonPath('data.session.status', 'completed')
        ->assertJsonPath('data.session.samples_count', 2)
        ->assertJsonPath('data.session.participants_count', 2)
        ->assertJsonCount(2, 'data.participants')
        ->assertJsonPath('data.participants.0.participant_id', $correct->id)
        ->assertJsonPath('data.participants.0.user.id', $correct->user->uuid)
        ->assertJsonPath('data.participants.0.is_complete', true)
        ->assertJsonPath('data.participants.0.metrics.effectiveness', '100.00')
        ->assertJsonPath('data.participants.0.metrics.miss_rate', '0.00')
        ->assertJsonPath('data.participants.0.metrics.false_alarm_rate', '0.00')
        ->assertJsonPath('data.participants.0.metrics.decisions', 2)
        ->assertJsonPath('data.participants.0.metrics.correct_decisions', 2)
        ->assertJsonPath('data.participants.0.metrics.defect_decisions', 1)
        ->assertJsonPath('data.participants.0.metrics.good_decisions', 1)
        ->assertJsonPath('data.participants.0.metrics.missed_defects', 0)
        ->assertJsonPath('data.participants.0.metrics.false_alarms', 0)
        ->assertJsonPath('data.participants.0.metrics.timed_out_samples', 0)
        ->assertJsonPath('data.participants.1.participant_id', $partial->id)
        ->assertJsonPath('data.participants.1.metrics.effectiveness', '0.00')
        ->assertJsonPath('data.participants.1.metrics.miss_rate', null)
        ->assertJsonPath('data.participants.1.metrics.false_alarm_rate', '100.00')
        ->assertJsonPath('data.participants.1.metrics.decisions', 1)
        ->assertJsonPath('data.participants.1.metrics.timed_out_samples', 1);
});

test('a manager can read the evaluation', function () {
    [$session, $samples] = evaluationSession($this);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect]);

    Sanctum::actingAs($this->manager);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonCount(1, 'data.participants')
        ->assertJsonPath('data.participants.0.metrics.effectiveness', '100.00');
});

test('an inspector cannot read the evaluation', function () {
    [$session, $samples] = evaluationSession($this);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect]);

    Sanctum::actingAs($this->inspector);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

test('the evaluation endpoint requires authentication', function () {
    [$session] = evaluationSession($this);

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

test('only a completed session can be evaluated', function (SessionStatus $status) {
    $session = InspectionSession::factory()->forTeam($this->team)->create([
        'status' => $status,
        'started_at' => $status === SessionStatus::InProgress ? now() : null,
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('status');
})->with([
    'draft' => [SessionStatus::Draft],
    'in progress' => [SessionStatus::InProgress],
]);

test('a force-closed session evaluates only the stored verdicts', function () {
    [$session, $samples] = evaluationSession($this);

    // The participant answered one sample and never finished; a force-close
    // must not invent a verdict or a timeout for the missing one.
    $unfinished = evaluationParticipant($session, $samples, [Verdict::Good], finished: false);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.participants.0.participant_id', $unfinished->id)
        ->assertJsonPath('data.participants.0.is_complete', false)
        ->assertJsonPath('data.participants.0.completed_at', null)
        ->assertJsonPath('data.participants.0.metrics.decisions', 1)
        ->assertJsonPath('data.participants.0.metrics.correct_decisions', 1)
        ->assertJsonPath('data.participants.0.metrics.effectiveness', '100.00')
        ->assertJsonPath('data.participants.0.metrics.timed_out_samples', 0);
});

test('a participant with no verdict at all gets null rates', function () {
    [$session, $samples] = evaluationSession($this);
    evaluationParticipant($session, $samples, [null, null], finished: false);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.participants.0.metrics.effectiveness', null)
        ->assertJsonPath('data.participants.0.metrics.miss_rate', null)
        ->assertJsonPath('data.participants.0.metrics.false_alarm_rate', null)
        ->assertJsonPath('data.participants.0.metrics.decisions', 0)
        ->assertJsonPath('data.participants.0.metrics.timed_out_samples', 2);
});

test('the evaluation never exposes repeatability', function () {
    [$session, $samples] = evaluationSession($this);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonMissingPath('data.session.between_agreement')
        ->assertJsonMissingPath('data.session.all_vs_standard')
        ->assertJsonMissingPath('data.participants.0.metrics.repeatability')
        ->assertJsonMissingPath('data.participants.0.metrics.thresholds');
});

test('session agreement judgement is derived from all_vs_standard', function () {
    // 4 samples, 3 inspectors, 100% all_vs_standard → acceptable
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect, Verdict::Defect, Verdict::Good]);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect, Verdict::Defect, Verdict::Good]);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect, Verdict::Defect, Verdict::Good]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.judgement', 'acceptable');

    // 50% all_vs_standard → unacceptable
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect, Verdict::Defect, Verdict::Defect]);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Good, Verdict::Defect, Verdict::Defect]);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect, Verdict::Defect, Verdict::Defect]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.judgement', 'unacceptable');

    // Null all_vs_standard (fewer than 2 participants) → null judgement
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
    ]);
    evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.judgement', null);
});

test('a deleted inspector still appears in the evaluation', function () {
    [$session, $samples] = evaluationSession($this);
    $participant = evaluationParticipant($session, $samples, [Verdict::Good, Verdict::Defect]);

    $participant->user->delete();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.participants.0.user.id', $participant->user->uuid)
        ->assertJsonPath('data.participants.0.user.name', $participant->user->name);
});

/**
 * A completed session with the given answer keys, in inspection order.
 *
 * @param  array<int, Verdict>  $answerKeys
 * @return array{0: InspectionSession, 1: array<int, SessionSample>}
 */
function agreementSession(object $test, array $answerKeys): array
{
    $partType = PartType::factory()->create();

    $session = InspectionSession::factory()->forTeam($test->team)->completed()->create([
        'part_type_id' => $partType->id,
        'response_time_limit_ms' => 90000,
    ]);

    $sessionSamples = [];

    foreach (array_values($answerKeys) as $index => $answerKey) {
        $sample = Sample::factory()->for($partType)->create(['answer_key' => $answerKey]);

        $sessionSamples[] = $session->sessionSamples()->create([
            'sample_id' => $sample->id,
            'answer_key' => $answerKey,
            'position' => $index + 1,
        ]);
    }

    return [$session, $sessionSamples];
}

test('session agreement compares every completed inspector with the answer key', function () {
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);

    // Siti and Ani disagree with Budi on sample 2. Everybody calls sample 4
    // defect while the key says good.
    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Defect,
    ]);
    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
    ]);
    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Defect,
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.participants_compared', 3)
        ->assertJsonPath('data.agreement.compared_samples', 4)
        ->assertJsonPath('data.agreement.excluded_samples', 0)
        ->assertJsonPath('data.agreement.between_agreement', '75.00')
        ->assertJsonPath('data.agreement.all_vs_standard', '50.00');
});

test('a timed out sample is excluded from session agreement', function () {
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);

    // Budi times out on sample 2, so that sample leaves both denominators.
    // The remaining three samples agree; two of them match the key.
    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Defect,
    ]);
    evaluationParticipant($session, $samples, [
        Verdict::Good,
        null,
        Verdict::Defect,
        Verdict::Defect,
    ]);
    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Defect,
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.participants_compared', 3)
        ->assertJsonPath('data.agreement.compared_samples', 3)
        ->assertJsonPath('data.agreement.excluded_samples', 1)
        ->assertJsonPath('data.agreement.between_agreement', '100.00')
        ->assertJsonPath('data.agreement.all_vs_standard', '66.67');
});

test('session agreement is undefined with fewer than two completed participants', function () {
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);

    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.participants_compared', 1)
        ->assertJsonPath('data.agreement.between_agreement', null)
        ->assertJsonPath('data.agreement.all_vs_standard', null);
});

test('session agreement is undefined when every sample is excluded', function () {
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);

    evaluationParticipant($session, $samples, [null, null, null, null]);
    evaluationParticipant($session, $samples, [null, null, null, null]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.participants_compared', 2)
        ->assertJsonPath('data.agreement.compared_samples', 0)
        ->assertJsonPath('data.agreement.excluded_samples', 4)
        ->assertJsonPath('data.agreement.between_agreement', null)
        ->assertJsonPath('data.agreement.all_vs_standard', null);
});

test('a force-closed session does not compare an unfinished participant', function () {
    [$session, $samples] = agreementSession($this, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);

    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);
    evaluationParticipant($session, $samples, [
        Verdict::Good,
        Verdict::Defect,
        Verdict::Defect,
        Verdict::Good,
    ]);

    // The admin force-closed the session while this participant was unfinished.
    // Their stored verdicts must not change the comparison.
    evaluationParticipant($session, $samples, [
        Verdict::Defect,
        Verdict::Good,
        Verdict::Good,
        Verdict::Defect,
    ], finished: false);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/sessions/'.$session->uuid.'/evaluation')
        ->assertOk()
        ->assertJsonPath('data.agreement.participants_compared', 2)
        ->assertJsonPath('data.agreement.compared_samples', 4)
        ->assertJsonPath('data.agreement.excluded_samples', 0)
        ->assertJsonPath('data.agreement.between_agreement', '100.00')
        ->assertJsonPath('data.agreement.all_vs_standard', '100.00')
        ->assertJsonPath('data.participants.2.is_complete', false);
});

<?php

use App\Enums\Judgement;
use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Enums\Verdict;
use App\Models\GrrEvaluation;
use App\Models\InspectionResult;
use App\Models\InspectionSession;
use App\Models\InspectorTrial;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\SampleKeyHistory;
use App\Models\SessionEvaluation;
use App\Models\SessionParticipant;
use App\Models\SessionSample;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

test('the user factory creates an active inspector with the expected casts', function () {
    $user = User::factory()->create();

    expect($user->exists)->toBeTrue()
        ->and($user->role)->toBe(Role::Inspector)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->registered_via)->toBe(RegistrationSource::SelfRegistered)
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->uuid)->toBeString()
        ->and($user->getRouteKeyName())->toBe('uuid')
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('the user factory states set sensitive columns that are not fillable', function () {
    $user = User::factory()->admin()->pending()->createdByAdmin()->create();

    expect($user->role)->toBe(Role::Admin)
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->registered_via)->toBe(RegistrationSource::Admin)
        ->and($user->must_change_password)->toBeTrue();
});

test('master data relations work', function () {
    $plant = Plant::factory()->create();
    $team = Team::factory()->for($plant)->create();
    $user = User::factory()->forTeam($team)->create();
    $partType = PartType::factory()->create();
    $sample = Sample::factory()->defect()->for($partType)->create();

    expect($team->plant->is($plant))->toBeTrue()
        ->and($plant->teams->contains($team))->toBeTrue()
        ->and($user->team->is($team))->toBeTrue()
        ->and($team->users->contains($user))->toBeTrue()
        ->and($sample->partType->is($partType))->toBeTrue()
        ->and($partType->samples->contains($sample))->toBeTrue()
        ->and($sample->answer_key)->toBe(Verdict::Defect);
});

test('sample key history relates to its sample and changer with enum casts', function () {
    $admin = User::factory()->admin()->create();
    $sample = Sample::factory()->good()->create();
    $history = SampleKeyHistory::factory()->create([
        'sample_id' => $sample->id,
        'changed_by' => $admin->id,
    ]);

    expect($history->sample->is($sample))->toBeTrue()
        ->and($history->changer->is($admin))->toBeTrue()
        ->and($history->old_key)->toBe(Verdict::Good)
        ->and($history->new_key)->toBe(Verdict::Defect)
        ->and($history->changed_at)->toBeInstanceOf(Carbon::class);
});

test('inspection session relations and casts work', function () {
    $session = InspectionSession::factory()->create();

    expect($session->plant->id)->toBe($session->team->plant_id)
        ->and($session->partType)->toBeInstanceOf(PartType::class)
        ->and($session->creator->role)->toBe(Role::Admin)
        ->and($session->session_date)->toBeInstanceOf(Carbon::class)
        ->and($session->trial_count)->toBe(3)
        ->and($session->status->value)->toBe('draft');

    $sessionSample = SessionSample::factory()->create(['session_id' => $session->id]);
    $participant = SessionParticipant::factory()->create(['session_id' => $session->id]);
    $evaluation = SessionEvaluation::factory()->create(['session_id' => $session->id]);

    expect($session->sessionSamples->contains($sessionSample))->toBeTrue()
        ->and($session->participants->contains($participant))->toBeTrue()
        ->and($session->evaluation->is($evaluation))->toBeTrue();
});

test('session sample keeps its own answer key and links to the session', function () {
    $session = InspectionSession::factory()->create();
    $sample = SessionSample::factory()->defect()->create([
        'session_id' => $session->id,
        'answer_key' => Verdict::Defect,
    ]);

    expect($sample->session->is($session))->toBeTrue()
        ->and($sample->sample)->toBeInstanceOf(Sample::class)
        ->and($sample->answer_key)->toBe(Verdict::Defect)
        ->and($sample->position)->toBeInt();
});

test('participant, trial, result and evaluation relations work', function () {
    $session = InspectionSession::factory()->create();
    $sessionSample = SessionSample::factory()->create(['session_id' => $session->id]);
    $participant = SessionParticipant::factory()->create(['session_id' => $session->id]);

    $trial = InspectorTrial::factory()->create([
        'participant_id' => $participant->id,
        'trial_number' => 2,
    ]);
    $result = InspectionResult::factory()->create([
        'participant_id' => $participant->id,
        'session_sample_id' => $sessionSample->id,
        'trial_number' => 3,
        'verdict' => Verdict::Good,
    ]);
    $grr = GrrEvaluation::factory()->create(['participant_id' => $participant->id]);

    expect($participant->session->is($session))->toBeTrue()
        ->and($participant->user->role)->toBe(Role::Inspector)
        ->and($participant->trials->contains($trial))->toBeTrue()
        ->and($participant->results->contains($result))->toBeTrue()
        ->and($participant->evaluation->is($grr))->toBeTrue();

    expect($trial->participant->is($participant))->toBeTrue()
        ->and($trial->trial_number)->toBe(2)
        ->and($trial->started_at)->toBeInstanceOf(Carbon::class)
        ->and($trial->submitted_at)->toBeNull();

    expect($result->participant->is($participant))->toBeTrue()
        ->and($result->sessionSample->is($sessionSample))->toBeTrue()
        ->and($result->verdict)->toBe(Verdict::Good)
        ->and($result->trial_number)->toBe(3);

    expect($grr->participant->is($participant))->toBeTrue()
        ->and($grr->judgement)->toBe(Judgement::Acceptable)
        ->and($grr->thresholds)->toBeArray()
        ->and($grr->repeatability)->toBeString()
        ->and($grr->computed_at)->toBeInstanceOf(Carbon::class);
});

test('every factory can create a valid record', function () {
    $factories = [
        Plant::factory(),
        Team::factory(),
        User::factory(),
        PartType::factory(),
        Sample::factory(),
        SampleKeyHistory::factory(),
        InspectionSession::factory(),
        SessionSample::factory(),
        SessionParticipant::factory(),
        InspectorTrial::factory(),
        InspectionResult::factory(),
        GrrEvaluation::factory(),
        SessionEvaluation::factory(),
    ];

    foreach ($factories as $factory) {
        expect($factory->create()->exists)->toBeTrue();
    }
});

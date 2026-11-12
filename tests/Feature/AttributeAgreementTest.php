<?php

use App\Actions\Grr\CalculateAttributeAgreement;
use App\Enums\Verdict;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Sample;
use App\Models\SessionParticipant;
use App\Models\User;

/**
 * Create one participant with one outcome per entry.
 *
 * Each entry is `[answer key, inspector verdict]`. A `null` verdict means the
 * inspector timed out on that sample.
 *
 * @param  array<int, array{0: Verdict, 1: Verdict|null}>  $outcomes
 */
function agreementParticipant(array $outcomes): SessionParticipant
{
    $partType = PartType::factory()->create();

    $session = InspectionSession::factory()->create([
        'part_type_id' => $partType->id,
    ]);

    $participant = $session->participants()->create([
        'user_id' => User::factory()->inspector()->create()->id,
    ]);

    foreach (array_values($outcomes) as $index => [$answerKey, $verdict]) {
        $sample = Sample::factory()->for($partType)->create(['answer_key' => $answerKey]);

        $sessionSample = $session->sessionSamples()->create([
            'sample_id' => $sample->id,
            'answer_key' => $answerKey,
            'position' => $index + 1,
        ]);

        $timedOut = $verdict === null;

        $participant->results()->create([
            'session_sample_id' => $sessionSample->id,
            'outcome' => $timedOut ? 'timed_out' : 'answered',
            'verdict' => $verdict,
            'response_time_ms' => $timedOut ? 90000 : 1500,
            'answered_at' => now(),
        ]);
    }

    return $participant;
}

test('all correct decisions give full effectiveness and zero error rates', function () {
    $participant = agreementParticipant([
        [Verdict::Good, Verdict::Good],
        [Verdict::Good, Verdict::Good],
        [Verdict::Defect, Verdict::Defect],
        [Verdict::Defect, Verdict::Defect],
    ]);

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->effectiveness)->toBe(100.0)
        ->and($result->missRate)->toBe(0.0)
        ->and($result->falseAlarmRate)->toBe(0.0)
        ->and($result->decisions)->toBe(4)
        ->and($result->correctDecisions)->toBe(4)
        ->and($result->defectDecisions)->toBe(2)
        ->and($result->goodDecisions)->toBe(2)
        ->and($result->missedDefects)->toBe(0)
        ->and($result->falseAlarms)->toBe(0)
        ->and($result->timedOutSamples)->toBe(0);
});

test('all wrong decisions give zero effectiveness and full error rates', function () {
    $participant = agreementParticipant([
        [Verdict::Good, Verdict::Defect],
        [Verdict::Good, Verdict::Defect],
        [Verdict::Good, Verdict::Defect],
        [Verdict::Defect, Verdict::Good],
        [Verdict::Defect, Verdict::Good],
    ]);

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->effectiveness)->toBe(0.0)
        ->and($result->missRate)->toBe(100.0)
        ->and($result->falseAlarmRate)->toBe(100.0)
        ->and($result->decisions)->toBe(5)
        ->and($result->correctDecisions)->toBe(0)
        ->and($result->defectDecisions)->toBe(2)
        ->and($result->goodDecisions)->toBe(3)
        ->and($result->missedDefects)->toBe(2)
        ->and($result->falseAlarms)->toBe(3);
});

test('mixed decisions follow the hand-computed rates', function () {
    // One good sample called defect, one good sample called good, one defect
    // sample called good: 1 of 3 decisions is correct.
    $participant = agreementParticipant([
        [Verdict::Good, Verdict::Defect],
        [Verdict::Good, Verdict::Good],
        [Verdict::Defect, Verdict::Good],
    ]);

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->effectiveness)->toBe(33.33)
        ->and($result->missRate)->toBe(100.0)
        ->and($result->falseAlarmRate)->toBe(50.0)
        ->and($result->decisions)->toBe(3)
        ->and($result->correctDecisions)->toBe(1)
        ->and($result->defectDecisions)->toBe(1)
        ->and($result->goodDecisions)->toBe(2)
        ->and($result->missedDefects)->toBe(1)
        ->and($result->falseAlarms)->toBe(1);
});

test('a session without defect samples returns a null miss rate', function () {
    $participant = agreementParticipant([
        [Verdict::Good, Verdict::Good],
        [Verdict::Good, Verdict::Defect],
    ]);

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->missRate)->toBeNull()
        ->and($result->falseAlarmRate)->toBe(50.0)
        ->and($result->effectiveness)->toBe(50.0)
        ->and($result->defectDecisions)->toBe(0)
        ->and($result->goodDecisions)->toBe(2);
});

test('a session without good samples returns a null false alarm rate', function () {
    $participant = agreementParticipant([
        [Verdict::Defect, Verdict::Defect],
        [Verdict::Defect, Verdict::Good],
    ]);

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->falseAlarmRate)->toBeNull()
        ->and($result->missRate)->toBe(50.0)
        ->and($result->effectiveness)->toBe(50.0)
        ->and($result->defectDecisions)->toBe(2)
        ->and($result->goodDecisions)->toBe(0);
});

test('timed out samples are missing data and stay out of every rate', function () {
    // The only defect sample timed out, so it is not a missed defect. The two
    // judged samples are both correct.
    $participant = agreementParticipant([
        [Verdict::Defect, null],
        [Verdict::Good, Verdict::Good],
        [Verdict::Good, Verdict::Good],
    ]);

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->timedOutSamples)->toBe(1)
        ->and($result->decisions)->toBe(2)
        ->and($result->correctDecisions)->toBe(2)
        ->and($result->effectiveness)->toBe(100.0)
        ->and($result->missRate)->toBeNull()
        ->and($result->falseAlarmRate)->toBe(0.0)
        ->and($result->missedDefects)->toBe(0);
});

test('a participant with no verdicts has no rates at all', function () {
    $participant = agreementParticipant([
        [Verdict::Good, null],
        [Verdict::Defect, null],
    ]);

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->effectiveness)->toBeNull()
        ->and($result->missRate)->toBeNull()
        ->and($result->falseAlarmRate)->toBeNull()
        ->and($result->decisions)->toBe(0)
        ->and($result->timedOutSamples)->toBe(2);
});

test('another participant results are not counted', function () {
    $participant = agreementParticipant([
        [Verdict::Good, Verdict::Good],
        [Verdict::Defect, Verdict::Defect],
    ]);

    $other = $participant->session->participants()->create([
        'user_id' => User::factory()->inspector()->create()->id,
    ]);

    foreach ($participant->session->sessionSamples()->orderBy('position')->get() as $sessionSample) {
        $other->results()->create([
            'session_sample_id' => $sessionSample->id,
            'outcome' => 'answered',
            'verdict' => $sessionSample->answer_key === Verdict::Good
                ? Verdict::Defect
                : Verdict::Good,
            'response_time_ms' => 1500,
            'answered_at' => now(),
        ]);
    }

    $result = app(CalculateAttributeAgreement::class)->handle($participant);

    expect($result->decisions)->toBe(2)
        ->and($result->correctDecisions)->toBe(2)
        ->and($result->effectiveness)->toBe(100.0);
});

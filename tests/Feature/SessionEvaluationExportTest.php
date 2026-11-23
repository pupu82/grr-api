<?php

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Enums\SessionStatus;
use App\Enums\Verdict;
use App\Jobs\GenerateSessionEvaluationExport;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\ReportExport;
use App\Models\Sample;
use App\Models\SessionParticipant;
use App\Models\SessionSample;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->inspector = User::factory()->inspector()->create();
    $this->team = Team::factory()->for(Plant::factory())->create();
});

/**
 * @return array{0: InspectionSession, 1: array<int, SessionSample>}
 */
function exportSession(object $test): array
{
    $partType = PartType::factory()->create();

    $session = InspectionSession::factory()->forTeam($test->team)->completed()->create([
        'part_type_id' => $partType->id,
        'response_time_limit_ms' => 90000,
    ]);

    $samples = [];

    foreach ([Verdict::Good, Verdict::Defect] as $index => $answerKey) {
        $sample = Sample::factory()->for($partType)->create(['answer_key' => $answerKey]);

        $samples[] = $session->sessionSamples()->create([
            'sample_id' => $sample->id,
            'part_type_id' => $partType->id,
            'part_type_name' => $partType->name,
            'answer_key' => $answerKey,
            'position' => $index + 1,
        ]);
    }

    return [$session, $samples];
}

/**
 * @param  array<int, Verdict>  $verdicts
 */
function exportParticipant(InspectionSession $session, array $samples, array $verdicts, Team $team): SessionParticipant
{
    $participant = $session->participants()->create([
        'user_id' => User::factory()->inspector()->forTeam($team)->create()->id,
    ]);

    $participant->forceFill([
        'started_at' => now()->subMinutes(10),
        'completed_at' => now()->subMinute(),
    ])->save();

    foreach (array_values($verdicts) as $index => $verdict) {
        $participant->results()->create([
            'session_sample_id' => $samples[$index]->id,
            'outcome' => 'answered',
            'verdict' => $verdict,
            'response_time_ms' => 1500,
            'answered_at' => now(),
        ]);
    }

    return $participant;
}

/**
 * @return array{0: InspectionSession, 1: SessionParticipant, 2: SessionParticipant}
 */
function exportCompletedStudy(object $test): array
{
    [$session, $samples] = exportSession($test);

    $correct = exportParticipant($session, $samples, [Verdict::Good, Verdict::Defect], $test->team);
    $swapped = exportParticipant($session, $samples, [Verdict::Defect, Verdict::Good], $test->team);

    return [$session, $correct, $swapped];
}

test('creating an export requires authentication', function () {
    [$session] = exportSession($this);

    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/exports', ['format' => 'csv'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

test('an inspector cannot request, poll or download an export', function () {
    [$session] = exportSession($this);
    $export = ReportExport::factory()->completed()->create([
        'session_id' => $session->id,
        'requested_by' => $this->admin->id,
    ]);

    Sanctum::actingAs($this->inspector);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/exports', ['format' => 'csv'])
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    $this->getJson('/api/v1/exports/'.$export->uuid)
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    $this->get('/api/v1/exports/'.$export->uuid.'/download')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

test('a manager can request and poll an export', function () {
    [$session] = exportSession($this);

    Queue::fake();

    Sanctum::actingAs($this->manager);

    $response = $this->postJson('/api/v1/sessions/'.$session->uuid.'/exports', ['format' => 'csv'])
        ->assertAccepted()
        ->assertJsonPath('data.session_id', $session->uuid)
        ->assertJsonPath('data.format', 'csv')
        ->assertJsonPath('data.status', 'pending');

    $exportId = $response->json('data.id');

    $this->getJson('/api/v1/exports/'.$exportId)
        ->assertOk()
        ->assertJsonPath('data.id', $exportId)
        ->assertJsonPath('data.status', 'pending');
});

test('requesting an export queues a job and returns the pending record', function () {
    [$session] = exportSession($this);

    Queue::fake();

    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/sessions/'.$session->uuid.'/exports', ['format' => 'xlsx'])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.format', 'xlsx')
        ->assertJsonPath('data.session_id', $session->uuid)
        ->assertJsonPath('data.download_url', null);

    Queue::assertPushed(GenerateSessionEvaluationExport::class);

    $this->assertDatabaseHas('report_exports', [
        'uuid' => $response->json('data.id'),
        'session_id' => $session->id,
        'requested_by' => $this->admin->id,
        'status' => ExportStatus::Pending->value,
        'format' => ExportFormat::Xlsx->value,
    ]);
});

test('an invalid format is rejected', function () {
    [$session] = exportSession($this);

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/exports', ['format' => 'pdf'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('format');
});

test('only a completed session can be exported', function (SessionStatus $status) {
    $session = InspectionSession::factory()->forTeam($this->team)->create(['status' => $status]);

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/sessions/'.$session->uuid.'/exports', ['format' => 'csv'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('status');
})->with([
    'draft' => [SessionStatus::Draft],
    'in progress' => [SessionStatus::InProgress],
]);

test('the job writes the same metric strings and judgement as the evaluation endpoint', function () {
    Storage::fake('local');
    config()->set('grr.exports.disk', 'local');

    [$session] = exportCompletedStudy($this);

    $export = ReportExport::factory()->create([
        'session_id' => $session->id,
        'requested_by' => $this->admin->id,
        'format' => ExportFormat::Csv,
    ]);

    Bus::dispatchSync(new GenerateSessionEvaluationExport($export));
    $export->refresh();

    expect($export->status)->toBe(ExportStatus::Completed)
        ->and(str_ends_with((string) $export->file_path, '.csv'))->toBeTrue()
        ->and($export->completed_at)->not->toBeNull();

    Storage::disk('local')->assertExists($export->file_path);

    $content = Storage::disk('local')->get($export->file_path);

    expect($content)->toContain('"record_type","session_id"')
        ->toContain('all_vs_standard')
        ->toContain('unacceptable')
        ->toContain('100.00')
        ->toContain('0.00')
        ->not->toContain('threshold')
        ->not->toContain('repeatability');
});

test('the job writes an xlsx file for the xlsx format', function () {
    Storage::fake('local');
    config()->set('grr.exports.disk', 'local');

    [$session] = exportCompletedStudy($this);

    $export = ReportExport::factory()->create([
        'session_id' => $session->id,
        'requested_by' => $this->admin->id,
        'format' => ExportFormat::Xlsx,
    ]);

    Bus::dispatchSync(new GenerateSessionEvaluationExport($export));
    $export->refresh();

    expect($export->status)->toBe(ExportStatus::Completed)
        ->and(str_ends_with((string) $export->file_path, '.xlsx'))->toBeTrue();

    Storage::disk('local')->assertExists($export->file_path);
});

test('a completed export can be polled and downloaded', function () {
    Storage::fake('local');
    config()->set('grr.exports.disk', 'local');

    [$session] = exportSession($this);
    $export = ReportExport::factory()->completed()->create([
        'session_id' => $session->id,
        'requested_by' => $this->admin->id,
        'format' => ExportFormat::Csv,
    ]);
    Storage::disk('local')->put($export->file_path, "record_type\nparticipant\n");

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/exports/'.$export->uuid)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.download_url', route('exports.download', $export->uuid));

    $response = $this->get('/api/v1/exports/'.$export->uuid.'/download')->assertOk();

    expect($response->streamedContent())->toContain('participant');
});

test('a pending export cannot be downloaded', function () {
    [$session] = exportSession($this);
    $export = ReportExport::factory()->create([
        'session_id' => $session->id,
        'requested_by' => $this->admin->id,
    ]);

    Sanctum::actingAs($this->admin);

    $this->get('/api/v1/exports/'.$export->uuid.'/download')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('export');
});

/**
 * @return array{0: InspectionSession, 1: array<int, SessionSample>}
 */
function multiPartTypeExportSession(object $test): array
{
    $primary = PartType::factory()->create(['name' => 'Primary Export']);
    $additional = PartType::factory()->create(['name' => 'Additional Export']);

    $session = InspectionSession::factory()->forTeam($test->team)->completed()->create([
        'part_type_id' => $primary->id,
        'response_time_limit_ms' => 90000,
    ]);

    $session->partTypes()->sync([$primary->id, $additional->id]);

    $samples = [];
    $position = 1;

    foreach ([[$primary, Verdict::Good], [$primary, Verdict::Defect], [$additional, Verdict::Good], [$additional, Verdict::Defect]] as [$partType, $answerKey]) {
        $sample = Sample::factory()->for($partType)->create(['answer_key' => $answerKey]);

        $samples[] = $session->sessionSamples()->create([
            'sample_id' => $sample->id,
            'part_type_id' => $partType->id,
            'part_type_name' => $partType->name,
            'answer_key' => $answerKey,
            'position' => $position++,
        ]);
    }

    return [$session, $samples];
}

test('the export writes per part type rows with the same metric strings', function () {
    Storage::fake('local');
    config()->set('grr.exports.disk', 'local');

    [$session, $samples] = multiPartTypeExportSession($this);

    exportParticipant($session, $samples, [Verdict::Good, Verdict::Good, Verdict::Defect, Verdict::Defect], $this->team);
    exportParticipant($session, $samples, [Verdict::Good, Verdict::Defect, Verdict::Good, Verdict::Good], $this->team);

    $export = ReportExport::factory()->create([
        'session_id' => $session->id,
        'requested_by' => $this->admin->id,
        'format' => ExportFormat::Csv,
    ]);

    Bus::dispatchSync(new GenerateSessionEvaluationExport($export));
    $export->refresh();

    expect($export->status)->toBe(ExportStatus::Completed);

    $content = Storage::disk('local')->get($export->file_path);
    $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($content)));
    $headers = array_shift($rows);
    $columns = array_flip($headers);

    $partTypeRows = array_values(array_filter($rows, fn (array $row): bool => $row[0] === 'part_type'));
    $participantPartTypeRows = array_values(array_filter($rows, fn (array $row): bool => $row[0] === 'part_type_participant'));

    expect($partTypeRows)->toHaveCount(2)
        ->and($participantPartTypeRows)->toHaveCount(4)
        ->and($partTypeRows[0][$columns['part_type_name']])->toBe('Primary Export')
        ->and($partTypeRows[0][$columns['samples_count']])->toBe('2')
        ->and($partTypeRows[0][$columns['all_vs_standard']])->toBe('50.00')
        ->and($partTypeRows[1][$columns['part_type_name']])->toBe('Additional Export')
        ->and($participantPartTypeRows[0][$columns['part_type_name']])->toBe('Primary Export')
        ->and($participantPartTypeRows[0][$columns['effectiveness']])->toBe('50.00')
        ->and($participantPartTypeRows[0][$columns['miss_rate']])->toBe('100.00')
        ->and($participantPartTypeRows[2][$columns['part_type_name']])->toBe('Additional Export')
        ->and($participantPartTypeRows[2][$columns['false_alarm_rate']])->toBe('100.00');
});

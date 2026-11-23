<?php

use App\Enums\ActivityAction;
use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use App\Models\UserImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @return array{0: User, 1: Team}
 */
function importAdminAndTeam(): array
{
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create(['name' => 'QC A']);

    return [$admin, $team];
}

function userImportCsv(string $content, string $name = 'users.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * @param  list<list<string|null>>  $rows
 */
function userImportXlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'grr-users-'.Str::uuid().'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile(
        $path,
        'users.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

function csvRow(string $code, string $name, string $team, string $reset = ''): string
{
    return sprintf('%s,%s,%s,%s', $code, $name, $team, $reset);
}

test('an unauthenticated user cannot preview or confirm an import', function () {
    [$admin] = importAdminAndTeam();
    $batch = UserImportBatch::factory()->create(['actor_id' => $admin->id]);

    $this->app['auth']->forgetGuards();

    $this->post('/api/v1/users/import/preview', [
        'file' => userImportCsv("employee_code,name,team_name\nEMP-1,Name,QC A\n"),
    ])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');

    $this->post('/api/v1/users/import/'.$batch->uuid.'/confirm')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');

    $this->get('/api/v1/users/import/template')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

test('managers and inspectors cannot import users', function (Role $role) {
    [$admin, $team] = importAdminAndTeam();
    $batch = UserImportBatch::factory()->create(['actor_id' => $admin->id]);
    $actor = User::factory()->create(['role' => $role]);

    Sanctum::actingAs($actor);

    $this->post('/api/v1/users/import/preview', [
        'file' => userImportCsv("employee_code,name,team_name\nEMP-1,Name,QC A\n"),
    ])->assertForbidden()->assertJsonPath('code', 'forbidden');

    $this->post('/api/v1/users/import/'.$batch->uuid.'/confirm')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    $this->get('/api/v1/users/import/template')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
})->with([
    'manager' => [Role::Manager],
    'inspector' => [Role::Inspector],
]);

test('an admin can download the xlsx user import template', function () {
    [$admin] = importAdminAndTeam();

    Sanctum::actingAs($admin);

    $response = $this->get('/api/v1/users/import/template')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('grr-user-import-template.xlsx')
        ->and($response->headers->get('Content-Type'))->toContain('spreadsheetml');

    $spreadsheet = IOFactory::load($response->getFile()->getPathname());

    expect($spreadsheet->getActiveSheet()->toArray(null, true, true, false)[0])
        ->toBe(['employee_code', 'name', 'team_name', 'reset_password']);
});

test('an admin can download the csv user import template', function () {
    [$admin] = importAdminAndTeam();

    Sanctum::actingAs($admin);

    $response = $this->get('/api/v1/users/import/template?format=csv')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('grr-user-import-template.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv');

    expect(file_get_contents($response->getFile()->getPathname()))
        ->toContain('"employee_code","name","team_name","reset_password"');
});

test('an invalid template format is rejected', function () {
    [$admin] = importAdminAndTeam();

    Sanctum::actingAs($admin);

    $this->get('/api/v1/users/import/template?format=pdf')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('format');
});

test('the preview accepts xlsx and csv files', function (UploadedFile $file) {
    [$admin] = importAdminAndTeam();

    Sanctum::actingAs($admin);

    $this->post('/api/v1/users/import/preview', ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('data.counts.create', 1)
        ->assertJsonPath('data.rows.0.employee_code', 'EMP-XLSX')
        ->assertJsonPath('data.rows.0.action', 'create');
})->with([
    'csv' => fn () => userImportCsv("employee_code,name,team_name,reset_password\nemp-xlsx,New Name,QC A,\n"),
    'xlsx' => fn () => userImportXlsx([
        ['employee_code', 'name', 'team_name', 'reset_password'],
        ['emp-xlsx', 'New Name', 'QC A', null],
    ]),
]);

test('an invalid file extension is rejected', function () {
    [$admin] = importAdminAndTeam();

    Sanctum::actingAs($admin);

    $this->post('/api/v1/users/import/preview', [
        'file' => UploadedFile::fake()->create('users.txt', 1, 'text/plain'),
    ])->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('file');
});

test('preview reports the decision for every row without changing accounts', function () {
    [$admin, $team] = importAdminAndTeam();
    $oldTeam = Team::factory()->create(['name' => 'Old Team']);
    $existing = User::factory()->inspector()->forTeam($oldTeam)->create([
        'employee_code' => 'EMP-EXIST',
        'name' => 'Old Name',
    ]);

    $csv = implode("\n", [
        'employee_code,name,team_name,reset_password',
        csvRow('emp-new', 'New Name', 'qc a'),
        csvRow('EMP-EXIST', 'New Existing Name', 'QC A'),
        csvRow('EMP-BLANK', '', 'QC A'),
        csvRow('EMP-DUP', 'First', 'QC A'),
        csvRow('EMP-DUP', 'Second', 'QC A'),
        '',
    ]);

    Sanctum::actingAs($admin);

    $this->post('/api/v1/users/import/preview', ['file' => userImportCsv($csv)])
        ->assertCreated()
        ->assertJsonPath('data.filename', 'users.csv')
        ->assertJsonPath('data.counts.create', 1)
        ->assertJsonPath('data.counts.update', 1)
        ->assertJsonPath('data.counts.skip', 3)
        ->assertJsonPath('data.rows.0.employee_code', 'EMP-NEW')
        ->assertJsonPath('data.rows.0.action', 'create')
        ->assertJsonPath('data.rows.0.team_id', $team->id)
        ->assertJsonPath('data.rows.1.user_id', $existing->uuid)
        ->assertJsonPath('data.rows.1.action', 'update')
        ->assertJsonPath('data.rows.2.action', 'skip')
        ->assertJsonPath('data.rows.2.issues.0', 'name_required')
        ->assertJsonPath('data.rows.3.issues.0', 'duplicate_in_file')
        ->assertJsonPath('data.rows.4.issues.0', 'duplicate_in_file');

    expect(User::where('employee_code', 'EMP-NEW')->exists())->toBeFalse()
        ->and($existing->fresh()->name)->toBe('Old Name')
        ->and(UserImportBatch::count())->toBe(1);
});

test('confirming creates active inspectors, updates existing users and returns temporary passwords once', function () {
    [$admin, $team] = importAdminAndTeam();
    $oldTeam = Team::factory()->create(['name' => 'Old Team']);
    $existing = User::factory()->inspector()->forTeam($oldTeam)->create([
        'employee_code' => 'EMP-EXIST',
        'name' => 'Old Name',
    ]);

    $csv = implode("\n", [
        'employee_code,name,team_name,reset_password',
        csvRow('emp-new', 'New Name', 'qc a'),
        csvRow('EMP-EXIST', 'New Existing Name', 'QC A'),
        csvRow('EMP-BLANK', '', 'QC A'),
        '',
    ]);

    Sanctum::actingAs($admin);

    $preview = $this->post('/api/v1/users/import/preview', ['file' => userImportCsv($csv)])->assertCreated();
    $batchId = $preview->json('data.id');

    $response = $this->post('/api/v1/users/import/'.$batchId.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.counts.created', 1)
        ->assertJsonPath('data.counts.updated', 1)
        ->assertJsonPath('data.counts.skipped', 1)
        ->assertJsonPath('data.results.0.action', 'created')
        ->assertJsonPath('data.results.1.action', 'updated')
        ->assertJsonPath('data.results.2.action', 'skipped');

    $temporaryPassword = $response->json('data.results.0.temporary_password');

    expect($temporaryPassword)->toBeString()->not->toBeEmpty()
        ->and($response->json('data.results.1.temporary_password'))->toBeNull();

    $newUser = User::where('employee_code', 'EMP-NEW')->firstOrFail();

    expect($newUser->role)->toBe(Role::Inspector)
        ->and($newUser->status)->toBe(UserStatus::Active)
        ->and($newUser->registered_via)->toBe(RegistrationSource::Admin)
        ->and($newUser->must_change_password)->toBeTrue()
        ->and($newUser->team_id)->toBe($team->id)
        ->and($newUser->approved_by)->toBe($admin->id)
        ->and(Hash::check($temporaryPassword, $newUser->password))->toBeTrue();

    $existing->refresh();

    expect($existing->name)->toBe('New Existing Name')
        ->and($existing->team_id)->toBe($team->id)
        ->and($existing->role)->toBe(Role::Inspector)
        ->and($existing->status)->toBe(UserStatus::Active)
        ->and(Hash::check('password', $existing->password))->toBeTrue();

    expect(UserImportBatch::where('uuid', $batchId)->firstOrFail()->confirmed_at)->not->toBeNull()
        ->and(ActivityLog::where('action', ActivityAction::UserImported)->exists())->toBeTrue()
        ->and(ActivityLog::where('action', ActivityAction::UserUpdated)->exists())->toBeTrue();
});

test('an explicit reset_password column resets an existing password and revokes tokens', function () {
    [$admin, $team] = importAdminAndTeam();
    $target = User::factory()->inspector()->forTeam($team)->create(['employee_code' => 'EMP-RESET']);
    $target->createToken('old-token');

    $csv = implode("\n", [
        'employee_code,name,team_name,reset_password',
        csvRow('EMP-RESET', 'Renamed', 'QC A', 'true'),
        '',
    ]);

    Sanctum::actingAs($admin);

    $preview = $this->post('/api/v1/users/import/preview', ['file' => userImportCsv($csv)])->assertCreated();
    $batchId = $preview->json('data.id');

    $response = $this->post('/api/v1/users/import/'.$batchId.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.counts.reset', 1)
        ->assertJsonPath('data.results.0.action', 'reset');

    $temporaryPassword = $response->json('data.results.0.temporary_password');
    $target->refresh();

    expect($temporaryPassword)->toBeString()
        ->and(Hash::check($temporaryPassword, $target->password))->toBeTrue()
        ->and($target->must_change_password)->toBeTrue()
        ->and($target->tokens()->count())->toBe(0)
        ->and($target->name)->toBe('Renamed')
        ->and(ActivityLog::where('action', ActivityAction::UserPasswordReset)->exists())->toBeTrue();
});

test('invalid, ambiguous, deleted and conflicting rows are skipped with a reported issue', function () {
    [$admin] = importAdminAndTeam();
    Team::factory()->create(['name' => 'qc a']);
    $importTeam = Team::factory()->create(['name' => 'Import Team']);
    $deleted = User::factory()->inspector()->create(['employee_code' => 'EMP-DELETED']);
    $deleted->delete();

    $csv = implode("\n", [
        'employee_code,name,team_name,reset_password',
        csvRow('EMP-AMBIG', 'Name', 'QC A'),
        csvRow('EMP-DELETED', 'Name', $importTeam->name),
        csvRow('EMP-TOO-LONG-CODE-0000000000000', 'Name', $importTeam->name),
        csvRow('EMP-MISSING', '', $importTeam->name),
        csvRow('EMP-NO-TEAM', 'Name', 'Unknown Team'),
        csvRow('EMP-BAD-RESET', 'Name', $importTeam->name, 'maybe'),
        '',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->post('/api/v1/users/import/preview', ['file' => userImportCsv($csv)])
        ->assertCreated()
        ->assertJsonPath('data.counts.skip', 6);

    $issues = collect($response->json('data.rows'))->mapWithKeys(
        fn (array $row): array => [$row['employee_code'] => $row['issues'][0] ?? null],
    );

    expect($issues->get('EMP-AMBIG'))->toBe('team_name_ambiguous')
        ->and($issues->get('EMP-DELETED'))->toBe('employee_code_deleted')
        ->and($issues->get('EMP-TOO-LONG-CODE-0000000000000'))->toBe('employee_code_too_long')
        ->and($issues->get('EMP-MISSING'))->toBe('name_required')
        ->and($issues->get('EMP-NO-TEAM'))->toBe('team_not_found')
        ->and($issues->get('EMP-BAD-RESET'))->toBe('reset_password_invalid');

    $batchId = $response->json('data.id');

    $this->post('/api/v1/users/import/'.$batchId.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.counts.skipped', 6);

    expect(User::where('employee_code', 'EMP-AMBIG')->exists())->toBeFalse();
});

test('another admin cannot confirm a preview they did not create', function () {
    [$admin] = importAdminAndTeam();
    $otherAdmin = User::factory()->admin()->create();
    $batch = UserImportBatch::factory()->create(['actor_id' => $otherAdmin->id]);

    Sanctum::actingAs($admin);

    $this->post('/api/v1/users/import/'.$batch->uuid.'/confirm')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

test('an expired or already confirmed preview cannot be reused', function () {
    [$admin] = importAdminAndTeam();
    $expired = UserImportBatch::factory()->create([
        'actor_id' => $admin->id,
        'expires_at' => now()->subMinute(),
    ]);
    $confirmed = UserImportBatch::factory()->confirmed()->create(['actor_id' => $admin->id]);

    Sanctum::actingAs($admin);

    $this->post('/api/v1/users/import/'.$expired->uuid.'/confirm')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('import_batch');

    $this->post('/api/v1/users/import/'.$confirmed->uuid.'/confirm')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('import_batch');
});

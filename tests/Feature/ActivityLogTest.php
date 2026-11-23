<?php

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->plant = Plant::factory()->create();
    $this->team = Team::factory()->for($this->plant)->create();
    $this->partType = PartType::factory()->create();
    $this->samples = Sample::factory()->count(2)->for($this->partType)->create();
    $this->inspectors = User::factory()->inspector()->count(2)->forTeam($this->team)->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function activityLogSessionPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'team_id' => $test->team->id,
        'part_type_id' => $test->partType->id,
        'session_date' => '2026-11-12',
        'sample_ids' => $test->samples->pluck('id')->all(),
        'participant_ids' => $test->inspectors->pluck('uuid')->all(),
    ], $overrides);
}

test('the activity log requires authentication', function () {
    $this->getJson('/api/v1/activity-logs')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

test('only an admin may read the activity log', function (string $role) {
    Sanctum::actingAs(User::factory()->{$role}()->create());

    $this->getJson('/api/v1/activity-logs')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
})->with(['manager', 'inspector']);

test('an admin lists activity newest first with pagination and public actor ids', function () {
    Sanctum::actingAs($this->admin);

    $oldest = ActivityLog::factory()->forActor($this->admin)->create([
        'created_at' => now()->subDays(2),
    ]);
    $newest = ActivityLog::factory()->forActor($this->admin)->create([
        'created_at' => now(),
    ]);

    foreach (range(1, 49) as $index) {
        ActivityLog::factory()->create(['created_at' => now()->subMinutes($index)]);
    }

    $response = $this->getJson('/api/v1/activity-logs')->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta' => ['total', 'per_page']]);

    expect($response->json('meta.total'))->toBe(51)
        ->and($response->json('meta.per_page'))->toBe(50)
        ->and($response->json('data'))->toHaveCount(50)
        ->and($response->json('data.0.id'))->toBe($newest->id)
        ->and($response->json('data.0.actor.id'))->toBe($this->admin->uuid)
        ->and($response->json('data.0.subject_type'))->toBe('user')
        ->and($response->json('data.0'))->not->toHaveKey('actor_id')
        ->and($response->json('data.49.id'))->not->toBe($oldest->id);

    $this->getJson('/api/v1/activity-logs?page=2')->assertOk()
        ->assertJsonPath('data.0.id', $oldest->id);
});

test('activity log filters accept one value or several', function () {
    Sanctum::actingAs($this->admin);

    $other = User::factory()->admin()->create();

    ActivityLog::factory()->forActor($this->admin)->forAction(ActivityAction::UserCreated)->create([
        'created_at' => now()->subDays(3),
    ]);
    ActivityLog::factory()->forActor($this->admin)->forAction(ActivityAction::UserDeleted)->create([
        'subject_type' => 'session',
        'subject_id' => 'session-1',
        'created_at' => now(),
    ]);
    ActivityLog::factory()->forActor($other)->forAction(ActivityAction::UserCreated)->create([
        'subject_type' => 'user',
        'subject_id' => 'user-1',
        'created_at' => now(),
    ]);

    expect($this->getJson('/api/v1/activity-logs?action=user.created')->json('meta.total'))->toBe(2)
        ->and($this->getJson('/api/v1/activity-logs?action[]=user.created&action[]=user.deleted')->json('meta.total'))->toBe(3)
        ->and($this->getJson('/api/v1/activity-logs?actor_id='.$other->uuid)->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/activity-logs?subject_type=session')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/activity-logs?subject_id=user-1')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/activity-logs?subject_type[]=user&subject_type[]=session')->json('meta.total'))->toBe(3)
        ->and($this->getJson('/api/v1/activity-logs?date_from='.now()->toDateString().'&date_to='.now()->toDateString())->json('meta.total'))->toBe(2);
});

test('activity log filters reject malformed values', function (string $query, string $field) {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/activity-logs?'.$query)
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($field);
})->with([
    ['action=user.promoted', 'action.0'],
    ['subject_type=sample', 'subject_type.0'],
    ['date_to=not-a-date', 'date_to'],
]);

test('user lifecycle events are logged with the admin actor', function () {
    Sanctum::actingAs($this->admin);

    $created = $this->postJson('/api/v1/users', [
        'employee_code' => 'EMP-9001',
        'name' => 'New Manager',
        'role' => 'manager',
    ])->assertCreated()->json('data.id');

    $pending = User::factory()->pending()->inspector()->create();
    $this->postJson('/api/v1/users/'.$pending->uuid.'/approve', ['team_id' => $this->team->id])->assertOk();

    $deactivated = User::factory()->inspector()->forTeam($this->team)->create();
    $this->postJson('/api/v1/users/'.$deactivated->uuid.'/deactivate')->assertOk();

    $reactivated = User::factory()->inspector()->inactive()->forTeam($this->team)->create();
    $this->postJson('/api/v1/users/'.$reactivated->uuid.'/reactivate')->assertOk();

    $deleted = User::factory()->inspector()->forTeam($this->team)->create();
    $this->deleteJson('/api/v1/users/'.$deleted->uuid)->assertNoContent();

    $reset = User::factory()->inspector()->forTeam($this->team)->create();
    $this->postJson('/api/v1/users/'.$reset->uuid.'/reset-password')->assertOk();

    $expected = [
        ActivityAction::UserCreated->value => $created,
        ActivityAction::UserApproved->value => $pending->uuid,
        ActivityAction::UserDeactivated->value => $deactivated->uuid,
        ActivityAction::UserReactivated->value => $reactivated->uuid,
        ActivityAction::UserDeleted->value => $deleted->uuid,
        ActivityAction::UserPasswordReset->value => $reset->uuid,
    ];

    foreach ($expected as $action => $subjectId) {
        $log = ActivityLog::query()->where('action', $action)->sole();

        expect($log->actor_id)->toBe($this->admin->id)
            ->and($log->actor_name)->toBe($this->admin->name)
            ->and($log->subject_type)->toBe('user')
            ->and($log->subject_id)->toBe($subjectId);
    }

    expect(ActivityLog::query()->where('action', ActivityAction::UserPasswordReset)->sole()->context['source'])
        ->toBe('api');
});

test('session lifecycle events are logged', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/sessions', activityLogSessionPayload($this))->assertCreated();

    $session = InspectionSession::query()->sole();

    $this->patchJson('/api/v1/sessions/'.$session->uuid, ['session_date' => '2026-11-20'])->assertOk();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/start')->assertOk();
    $this->postJson('/api/v1/sessions/'.$session->uuid.'/close')->assertOk();

    $this->postJson('/api/v1/sessions', activityLogSessionPayload($this, ['session_date' => '2026-11-21']))
        ->assertCreated();
    $draft = InspectionSession::query()->whereKeyNot($session->getKey())->sole();

    $this->deleteJson('/api/v1/sessions/'.$draft->uuid)->assertNoContent();

    $expected = [
        ActivityAction::SessionCreated->value => 2,
        ActivityAction::SessionUpdated->value => 1,
        ActivityAction::SessionStarted->value => 1,
        ActivityAction::SessionClosed->value => 1,
        ActivityAction::SessionDeleted->value => 1,
    ];

    foreach ($expected as $action => $count) {
        $logs = ActivityLog::query()->where('action', $action)->get();

        expect($logs)->toHaveCount($count);

        foreach ($logs as $log) {
            expect($log->actor_id)->toBe($this->admin->id)
                ->and($log->subject_type)->toBe('session')
                ->and($log->subject_id)->not->toBeNull();
        }
    }

    expect(ActivityLog::query()->where('action', ActivityAction::SessionUpdated)->sole()->context['fields'])
        ->toBe(['session_date']);
});

test('temporary passwords and tokens never enter the activity log', function () {
    Sanctum::actingAs($this->admin);

    $temporaryPassword = $this->postJson('/api/v1/users', [
        'employee_code' => 'EMP-9002',
        'name' => 'New Inspector',
        'role' => 'inspector',
    ])->assertCreated()->json('temporary_password');

    $resetTarget = User::factory()->inspector()->forTeam($this->team)->create();
    $resetPassword = $this->postJson('/api/v1/users/'.$resetTarget->uuid.'/reset-password')
        ->assertOk()
        ->json('temporary_password');

    app(LogActivity::class)->handle(
        ActivityAction::UserCreated,
        $this->admin,
        'user',
        $resetTarget->uuid,
        [
            'password' => 'must-be-removed',
            'api_token' => 'must-be-removed',
            'nested' => ['secret' => 'must-be-removed', 'safe' => 'kept'],
            'employee_code' => $resetTarget->employee_code,
        ],
    );

    $logs = ActivityLog::query()->get();

    foreach ($logs as $log) {
        expect($log->context)->not->toHaveKey('password')
            ->and($log->context)->not->toHaveKey('api_token');
    }

    $sanitized = $logs->last()->context;

    expect($sanitized['nested'])->toBe(['safe' => 'kept'])
        ->and(json_encode($logs->toArray()))->not->toContain($temporaryPassword)
        ->and(json_encode($logs->toArray()))->not->toContain($resetPassword)
        ->and(json_encode($logs->toArray()))->not->toContain('must-be-removed');
});

test('the actor snapshot survives a rename', function () {
    Sanctum::actingAs($this->admin);
    $originalName = $this->admin->name;

    $this->postJson('/api/v1/users', [
        'employee_code' => 'EMP-9003',
        'name' => 'Renamed Target',
        'role' => 'inspector',
    ])->assertCreated();

    $this->admin->update(['name' => 'New Name']);

    $log = $this->getJson('/api/v1/activity-logs')->assertOk()->json('data.0');

    expect($log['actor_name'])->toBe($originalName)
        ->and($log['actor']['name'])->toBe('New Name');
});

test('the break-glass reset is logged as a system action', function () {
    $admin = User::factory()->admin()->create(['employee_code' => 'ADMIN009']);

    Artisan::call('grr:reset-password', [
        'employee_code' => 'ADMIN009',
        '--force' => true,
    ]);

    preg_match('/Temporary password: (\S+)/', Artisan::output(), $matches);

    $log = ActivityLog::query()->where('action', ActivityAction::UserPasswordReset)->sole();

    expect($log->actor_id)->toBeNull()
        ->and($log->actor_name)->toBe('System')
        ->and($log->context['source'])->toBe('break_glass_command')
        ->and($log->subject_id)->toBe($admin->uuid)
        ->and(json_encode($log->context))->not->toContain($matches[1]);
});

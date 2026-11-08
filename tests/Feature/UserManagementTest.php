<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('every protected user route requires authentication', function (string $method, string $uri) {
    $target = User::factory()->create();

    $this->json($method, str_replace('{user}', $target->uuid, $uri))
        ->assertUnauthorized();
})->with([
    ['GET', '/api/v1/user'],
    ['POST', '/api/v1/logout'],
    ['POST', '/api/v1/password'],
    ['GET', '/api/v1/users'],
    ['POST', '/api/v1/users'],
    ['GET', '/api/v1/users/{user}'],
    ['POST', '/api/v1/users/{user}/approve'],
    ['POST', '/api/v1/users/{user}/deactivate'],
    ['DELETE', '/api/v1/users/{user}'],
]);

test('admin routes are forbidden for inspectors and managers', function (string $role, string $method, string $uri) {
    $actor = User::factory()->create(['role' => Role::from($role)]);
    $target = User::factory()->create();

    Sanctum::actingAs($actor);

    $this->json($method, str_replace('{user}', $target->uuid, $uri))
        ->assertForbidden();
})->with(['inspector', 'manager'])->with([
    ['GET', '/api/v1/users'],
    ['POST', '/api/v1/users'],
    ['GET', '/api/v1/users/{user}'],
    ['POST', '/api/v1/users/{user}/approve'],
    ['POST', '/api/v1/users/{user}/deactivate'],
    ['DELETE', '/api/v1/users/{user}'],
]);

test('a user can view their own account but not another account', function () {
    $inspector = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($inspector);

    $this->getJson('/api/v1/users/'.$inspector->uuid)->assertOk();
    $this->getJson('/api/v1/users/'.$other->uuid)->assertForbidden();
});

test('an inactive account is rejected on every authenticated route', function () {
    Sanctum::actingAs(User::factory()->inactive()->create());

    $this->getJson('/api/v1/user')
        ->assertForbidden()
        ->assertJson(['message' => 'Your account has been deactivated.']);
});

test('an admin can list users without exposing internal fields', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/users')->assertOk();

    expect($response->json('data'))->toHaveCount(4);

    $first = $response->json('data.0');

    expect($first)->toHaveKeys(['id', 'employee_code', 'name', 'role', 'status'])
        ->and($first)->not->toHaveKey('password')
        ->and($first)->not->toHaveKey('uuid')
        ->and($first['id'])->toBeString();
});

test('an admin can filter the user list by status', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->pending()->count(2)->create();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/users?status=pending')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('an admin can create an active account with a server-generated temporary password', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/users', [
        'employee_code' => 'EMP-5000',
        'name' => 'New Manager',
        'role' => 'manager',
        'team_id' => $team->id,
    ])->assertCreated()
        ->assertJsonPath('data.role', 'manager')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.must_change_password', true)
        ->assertJsonStructure(['data' => ['id', 'employee_code'], 'temporary_password']);

    $temporaryPassword = $response->json('temporary_password');

    $user = User::query()->where('employee_code', 'EMP-5000')->firstOrFail();

    expect($temporaryPassword)->toBeString()->toHaveLength(12)
        ->and(Hash::check($temporaryPassword, $user->password))->toBeTrue()
        ->and($user->role)->toBe(Role::Manager)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->registered_via->value)->toBe('admin')
        ->and($user->approved_by)->toBe($admin->id)
        ->and($user->team_id)->toBe($team->id);

    // The temporary password is returned once and never again.
    $this->getJson('/api/v1/users/'.$user->uuid)
        ->assertOk()
        ->assertJsonMissingPath('temporary_password');
});

test('a password chosen by the admin is ignored when creating an account', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson('/api/v1/users', [
        'employee_code' => 'EMP-5100',
        'name' => 'Chosen Password',
        'password' => 'chosen-by-admin',
        'role' => 'inspector',
    ])->assertCreated();

    $user = User::query()->where('employee_code', 'EMP-5100')->firstOrFail();

    expect(Hash::check('chosen-by-admin', $user->password))->toBeFalse()
        ->and(Hash::check($response->json('temporary_password'), $user->password))->toBeTrue();
});

test('an admin can approve a pending account and assign a team', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();
    $pending = User::factory()->pending()->create(['employee_code' => 'EMP-6000']);
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/users/'.$pending->uuid.'/approve', [
        'team_id' => $team->id,
    ])->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.team.id', $team->id);

    $pending->refresh();

    expect($pending->status)->toBe(UserStatus::Active)
        ->and($pending->team_id)->toBe($team->id)
        ->and($pending->approved_by)->toBe($admin->id)
        ->and($pending->approved_at)->not->toBeNull();
});

test('an account that is not pending cannot be approved again', function () {
    $admin = User::factory()->admin()->create();
    $active = User::factory()->create();
    $team = Team::factory()->create();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/users/'.$active->uuid.'/approve', [
        'team_id' => $team->id,
    ])->assertStatus(422)->assertJsonValidationErrors('user');
});

test('an admin cannot deactivate or delete their own account', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/users/'.$admin->uuid.'/deactivate')->assertForbidden();
    $this->deleteJson('/api/v1/users/'.$admin->uuid)->assertForbidden();
});

test('the last active admin cannot be deactivated or deleted', function () {
    $first = User::factory()->admin()->create();
    $second = User::factory()->admin()->create();
    Sanctum::actingAs($first);

    $this->postJson('/api/v1/users/'.$second->uuid.'/deactivate')->assertOk();
    $this->postJson('/api/v1/users/'.$first->uuid.'/deactivate')->assertForbidden();
    $this->deleteJson('/api/v1/users/'.$first->uuid)->assertForbidden();
});

test('an admin can deactivate another account and its tokens are revoked', function () {
    $admin = User::factory()->admin()->create();
    $victim = User::factory()->create();
    $victim->createToken('api');
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/users/'.$victim->uuid.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    expect($victim->refresh()->status)->toBe(UserStatus::Inactive)
        ->and($victim->tokens()->count())->toBe(0);
});

test('an admin can soft delete another account', function () {
    $admin = User::factory()->admin()->create();
    $victim = User::factory()->create();
    Sanctum::actingAs($admin);

    $this->deleteJson('/api/v1/users/'.$victim->uuid)->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $victim->id]);
});

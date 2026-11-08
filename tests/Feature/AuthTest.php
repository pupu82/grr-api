<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

function grrRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'employee_code' => 'EMP-1000',
        'name' => 'Siti Inspector',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ], $overrides);
}

test('self registration creates a pending inspector', function () {
    $this->postJson('/api/v1/register', grrRegistrationPayload())
        ->assertStatus(202)
        ->assertExactJson(['message' => 'Registration received, pending approval.']);

    $user = User::query()->where('employee_code', 'EMP-1000')->firstOrFail();

    expect($user->role)->toBe(Role::Inspector)
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->registered_via->value)->toBe('self')
        ->and($user->team_id)->toBeNull()
        ->and($user->approved_by)->toBeNull()
        ->and($user->approved_at)->toBeNull();
});

test('self registration ignores a role sent by the client', function () {
    $this->postJson('/api/v1/register', grrRegistrationPayload(['role' => 'admin']))
        ->assertStatus(202);

    expect(User::query()->where('employee_code', 'EMP-1000')->firstOrFail()->role)
        ->toBe(Role::Inspector);
});

test('registration returns the same response whether the code exists or not', function () {
    User::factory()->create(['employee_code' => 'EMP-1000']);

    $existing = $this->postJson('/api/v1/register', grrRegistrationPayload(['employee_code' => 'EMP-1000']));
    $unknown = $this->postJson('/api/v1/register', grrRegistrationPayload(['employee_code' => 'EMP-2000']));

    expect($existing->status())->toBe($unknown->status())
        ->and($existing->json())->toBe($unknown->json());

    expect(User::query()->where('employee_code', 'EMP-1000')->count())->toBe(1)
        ->and(User::query()->where('employee_code', 'EMP-2000')->count())->toBe(1);
});

test('registration validates the password confirmation', function () {
    $this->postJson('/api/v1/register', grrRegistrationPayload([
        'password_confirmation' => 'does-not-match',
    ]))->assertStatus(422)->assertJsonValidationErrors('password');
});

test('registration is throttled after five attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/register', grrRegistrationPayload([
            'employee_code' => "EMP-30{$attempt}",
        ]))->assertStatus(202);
    }

    $this->postJson('/api/v1/register', grrRegistrationPayload([
        'employee_code' => 'EMP-306',
    ]))->assertStatus(429);
});

test('login returns a token for valid credentials', function () {
    $user = User::factory()->create(['employee_code' => 'EMP-1000']);

    $response = $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => ['id', 'employee_code', 'name', 'role', 'status'],
        ]);

    $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->uuid)
        ->assertJsonMissingPath('data.password');
});

test('login gives the same generic error for a wrong password and an unknown code', function () {
    User::factory()->create(['employee_code' => 'EMP-1000']);

    $wrongPassword = $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'wrong-password',
    ]);
    $unknownCode = $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-9999',
        'password' => 'wrong-password',
    ]);

    expect($wrongPassword->status())->toBe(401)
        ->and($unknownCode->status())->toBe(401)
        ->and($wrongPassword->json())->toBe($unknownCode->json())
        ->and($wrongPassword->json('message'))->toBe('The provided credentials are incorrect.');
});

test('a pending account learns its status only after the correct password', function () {
    User::factory()->pending()->create(['employee_code' => 'EMP-1000']);

    $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'wrong-password',
    ])->assertStatus(401)->assertJson(['message' => 'The provided credentials are incorrect.']);

    $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'password',
    ])->assertStatus(403)->assertJson(['message' => 'Your account is pending approval.']);
});

test('an inactive account cannot log in', function () {
    User::factory()->inactive()->create(['employee_code' => 'EMP-1000']);

    $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'password',
    ])->assertStatus(403)->assertJson(['message' => 'Your account has been deactivated.']);
});

test('logout revokes the token', function () {
    $user = User::factory()->create(['employee_code' => 'EMP-1000']);
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});

test('login is throttled after five attempts', function () {
    User::factory()->create(['employee_code' => 'EMP-1000']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/login', [
            'employee_code' => 'EMP-1000',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

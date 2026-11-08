<?php

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| API error contract
|--------------------------------------------------------------------------
|
| Flutter branches on the stable `code` field, never on `message`. These tests
| pin every code so a rename or a dropped code fails loudly.
|
*/

test('unauthenticated requests return the unauthenticated code', function () {
    $this->getJson('/api/v1/plants')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('login failures return the same generic code for a wrong password and an unknown code', function () {
    User::factory()->create(['employee_code' => 'EMP-1000']);

    $wrongPassword = $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'wrong-password',
    ]);
    $unknownCode = $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-9999',
        'password' => 'wrong-password',
    ]);

    expect($wrongPassword->json())->toBe($unknownCode->json());

    $wrongPassword->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_credentials')
        ->assertJsonPath('message', 'The provided credentials are incorrect.');
});

test('account status errors return stable codes after the password is verified', function () {
    User::factory()->pending()->create(['employee_code' => 'EMP-PENDING']);
    User::factory()->inactive()->create(['employee_code' => 'EMP-INACTIVE']);

    $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-PENDING',
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('code', 'account_pending')
        ->assertJsonPath('message', 'Your account is pending approval.');

    $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-INACTIVE',
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('code', 'account_inactive')
        ->assertJsonPath('message', 'Your account has been deactivated.');
});

test('an outstanding password change returns the password change required code', function () {
    Sanctum::actingAs(User::factory()->admin()->create(['must_change_password' => true]));

    $this->getJson('/api/v1/plants')
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required')
        ->assertJsonPath('message', 'You must change your password before continuing.');
});

test('authorization failures return the forbidden code', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/plants')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

test('validation failures keep the Laravel error shape and add a code', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/v1/plants', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonStructure(['message', 'code', 'errors'])
        ->assertJsonValidationErrors(['code', 'name']);
});

test('missing records return the not found code', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson('/api/v1/users/'.Str::uuid())
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

test('unsupported methods return the method not allowed code', function () {
    $this->getJson('/api/v1/login')
        ->assertStatus(405)
        ->assertJsonPath('code', 'method_not_allowed');
});

test('throttled requests return the too many requests code', function () {
    User::factory()->create(['employee_code' => 'EMP-1000']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/login', [
            'employee_code' => 'EMP-1000',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/v1/login', [
        'employee_code' => 'EMP-1000',
        'password' => 'wrong-password',
    ])->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests');
});

test('the error code enum keeps its public values stable', function () {
    expect(array_column(ErrorCode::cases(), 'value'))->toBe([
        'unauthenticated',
        'invalid_credentials',
        'account_pending',
        'account_inactive',
        'password_change_required',
        'forbidden',
        'not_found',
        'method_not_allowed',
        'validation_failed',
        'too_many_requests',
        'http_error',
    ]);
});

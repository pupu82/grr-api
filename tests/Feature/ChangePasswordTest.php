<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('a temporary password blocks everything except the profile, logout and the password change', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/user')->assertOk();

    $this->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJson(['message' => 'You must change your password before continuing.']);

    $this->getJson('/api/v1/plants')
        ->assertForbidden()
        ->assertJson(['message' => 'You must change your password before continuing.']);
});

test('changing the password verifies the current password and clears the flag', function () {
    $user = User::factory()->admin()->create(['must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    $this->postJson('/api/v1/password', [
        'current_password' => 'password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertOk()->assertJson(['message' => 'Password changed.']);

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('new-secret-password', $user->password))->toBeTrue();

    $this->getJson('/api/v1/plants')->assertOk();

    $this->postJson('/api/v1/login', [
        'employee_code' => $user->employee_code,
        'password' => 'new-secret-password',
    ])->assertOk();
});

test('the new password must be different and confirmed', function () {
    $user = User::factory()->admin()->create(['must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/password', [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    $this->postJson('/api/v1/password', [
        'current_password' => 'password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'different-password',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

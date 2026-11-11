<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('the break-glass command rescues the last admin and prints the password once', function () {
    $admin = User::factory()->admin()->create(['employee_code' => 'ADMIN001']);
    $admin->createToken('api');
    $admin->createToken('api');

    $exitCode = Artisan::call('grr:reset-password', [
        'employee_code' => 'ADMIN001',
        '--force' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('ADMIN001')
        ->and($output)->toContain('All existing tokens were revoked');

    preg_match('/Temporary password: (\S+)/', $output, $matches);

    expect($matches)->toHaveCount(2);

    $temporaryPassword = $matches[1];

    $admin->refresh();

    expect(Hash::check($temporaryPassword, $admin->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse()
        ->and($admin->must_change_password)->toBeTrue()
        ->and($admin->tokens()->count())->toBe(0);
});

test('the break-glass command refuses to run non-interactively without force', function () {
    $user = User::factory()->create(['employee_code' => 'EMP-8100']);

    $exitCode = Artisan::call('grr:reset-password', [
        'employee_code' => 'EMP-8100',
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('--force')
        ->and(Hash::check('password', $user->refresh()->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse();
});

test('the break-glass command aborts when the operator declines the confirmation', function () {
    $user = User::factory()->create(['employee_code' => 'EMP-8400']);

    $this->artisan('grr:reset-password', ['employee_code' => 'EMP-8400'])
        ->expectsConfirmation("Reset the password for EMP-8400 ({$user->name})?", 'no')
        ->assertExitCode(1);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse();
});

test('the break-glass command rejects an unknown employee code', function () {
    $exitCode = Artisan::call('grr:reset-password', [
        'employee_code' => 'EMP-9999',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('No account uses employee code');
});

test('the break-glass command refuses a soft-deleted account', function () {
    $user = User::factory()->create(['employee_code' => 'EMP-8200']);
    $user->delete();

    $exitCode = Artisan::call('grr:reset-password', [
        'employee_code' => 'EMP-8200',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('No account uses employee code');
});

test('the break-glass command keeps the account role and status', function () {
    $user = User::factory()->pending()->create(['employee_code' => 'EMP-8300']);

    $exitCode = Artisan::call('grr:reset-password', [
        'employee_code' => 'EMP-8300',
        '--force' => true,
    ]);

    $user->refresh();

    expect($exitCode)->toBe(0)
        ->and($user->status->value)->toBe('pending')
        ->and($user->role->value)->toBe('inspector')
        ->and($user->must_change_password)->toBeTrue();
});

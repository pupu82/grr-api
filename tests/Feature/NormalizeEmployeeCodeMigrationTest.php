<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

function grrEmployeeCodeMigration(): object
{
    return require database_path('migrations/2026_11_23_092519_normalize_employee_code_in_users_table.php');
}

test('the employee code migration uppercases existing codes', function () {
    $user = User::factory()->create(['employee_code' => 'EMP-2000']);
    DB::table('users')->where('id', $user->id)->update(['employee_code' => 'emp-2000']);

    grrEmployeeCodeMigration()->up();

    expect(DB::table('users')->where('id', $user->id)->value('employee_code'))->toBe('EMP-2000');
});

test('the employee code migration refuses case-insensitive duplicates', function () {
    $first = User::factory()->create(['employee_code' => 'EMP-1000']);
    $second = User::factory()->create(['employee_code' => 'EMP-1001']);
    DB::table('users')->where('id', $second->id)->update(['employee_code' => 'emp-1000']);

    expect(fn () => grrEmployeeCodeMigration()->up())->toThrow(RuntimeException::class);
});

<?php

use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('the seeder creates the initial plants, part types and configured admin', function () {
    config(['grr.admin_seed.password' => 'temporary-secret']);

    $this->seed(DatabaseSeeder::class);

    expect(Plant::count())->toBe(16)
        ->and(Plant::where('code', 'BCP')->exists())->toBeTrue()
        ->and(Plant::where('code', 'WAREHOUSE-G')->exists())->toBeTrue()
        ->and(PartType::count())->toBe(4);

    $admin = User::query()
        ->where('employee_code', config('grr.admin_seed.employee_code'))
        ->firstOrFail();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->registered_via)->toBe(RegistrationSource::Admin)
        ->and($admin->must_change_password)->toBeTrue()
        ->and(Hash::check('temporary-secret', $admin->password))->toBeTrue();
});

test('the seeder skips the admin account when no password is configured', function () {
    config(['grr.admin_seed.password' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('role', Role::Admin->value)->exists())->toBeFalse();
});

test('seeding twice does not duplicate rows', function () {
    config(['grr.admin_seed.password' => 'temporary-secret']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Plant::count())->toBe(16)
        ->and(PartType::count())->toBe(4)
        ->and(User::query()->count())->toBe(1);
});

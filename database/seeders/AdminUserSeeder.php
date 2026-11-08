<?php

namespace Database\Seeders;

use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial admin account from the environment.
     *
     * The password never lives in code: it is read from config, which reads
     * SEED_ADMIN_PASSWORD from the environment. Without it, nothing is seeded.
     */
    public function run(): void
    {
        $employeeCode = config('grr.admin_seed.employee_code');
        $password = config('grr.admin_seed.password');

        if (blank($password)) {
            $this->command?->warn('Skipping admin account: SEED_ADMIN_PASSWORD is not set.');

            return;
        }

        if (User::withTrashed()->where('employee_code', $employeeCode)->exists()) {
            return;
        }

        $admin = new User;
        $admin->employee_code = $employeeCode;
        $admin->name = config('grr.admin_seed.name');
        $admin->password = $password;
        $admin->role = Role::Admin;
        $admin->status = UserStatus::Active;
        $admin->registered_via = RegistrationSource::Admin;
        $admin->must_change_password = true;
        $admin->approved_at = now();
        $admin->save();
    }
}

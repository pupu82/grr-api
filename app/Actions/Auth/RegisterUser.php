<?php

namespace App\Actions\Auth;

use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class RegisterUser
{
    /**
     * Create a pending inspector account, or silently do nothing when the
     * employee code is already taken. The caller must return the same response
     * in both cases so the endpoint never reveals whether a code exists.
     *
     * @param  array{employee_code: string, name: string, password: string}  $attributes
     */
    public function handle(array $attributes): void
    {
        if (User::query()->where('employee_code', $attributes['employee_code'])->exists()) {
            Log::info('Duplicate registration attempt.', [
                'employee_code' => $attributes['employee_code'],
            ]);

            return;
        }

        $user = new User;
        $user->fill([
            'employee_code' => $attributes['employee_code'],
            'name' => $attributes['name'],
            'password' => $attributes['password'],
        ]);
        $user->role = Role::Inspector;
        $user->status = UserStatus::Pending;
        $user->registered_via = RegistrationSource::SelfRegistered;

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            Log::info('Duplicate registration attempt.', [
                'employee_code' => $attributes['employee_code'],
            ]);
        }
    }
}

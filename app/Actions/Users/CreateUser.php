<?php

namespace App\Actions\Users;

use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

class CreateUser
{
    /**
     * Create an active account on behalf of an admin. The account is registered
     * via admin, gets the server-generated temporary password and must change
     * it on first login.
     *
     * @param  array{employee_code: string, name: string, role: Role|string, team_id?: int|null}  $attributes
     */
    public function handle(User $actor, array $attributes, string $temporaryPassword): User
    {
        $user = new User;
        $user->fill([
            'employee_code' => $attributes['employee_code'],
            'name' => $attributes['name'],
            'password' => $temporaryPassword,
            'team_id' => $attributes['team_id'] ?? null,
        ]);
        $user->role = $attributes['role'] instanceof Role
            ? $attributes['role']
            : Role::from($attributes['role']);
        $user->status = UserStatus::Active;
        $user->registered_via = RegistrationSource::Admin;
        $user->must_change_password = true;
        $user->approved_by = $actor->id;
        $user->approved_at = now();
        $user->save();

        return $user;
    }
}

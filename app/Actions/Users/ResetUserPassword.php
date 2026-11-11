<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetUserPassword
{
    /**
     * Replace a forgotten password with a server-generated temporary one and
     * revoke every token the account still holds.
     *
     * The caller decides who may do this and shows the temporary password to
     * the operator exactly once. A self-service password change still requires
     * the current password.
     */
    public function handle(User $target, string $temporaryPassword): User
    {
        return DB::transaction(function () use ($target, $temporaryPassword): User {
            $target->password = $temporaryPassword;
            $target->must_change_password = true;
            $target->save();

            // The old tokens must die with the old password.
            $target->tokens()->delete();

            return $target;
        });
    }
}

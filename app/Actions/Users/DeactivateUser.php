<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;

class DeactivateUser
{
    /**
     * Deactivate an account and revoke any token it still holds.
     */
    public function handle(User $target): User
    {
        $target->status = UserStatus::Inactive;
        $target->save();

        $target->tokens()->delete();

        return $target;
    }
}

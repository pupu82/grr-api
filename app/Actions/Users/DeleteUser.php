<?php

namespace App\Actions\Users;

use App\Models\User;

class DeleteUser
{
    /**
     * Soft delete an account and revoke any token it still holds.
     */
    public function handle(User $target): void
    {
        $target->tokens()->delete();
        $target->delete();
    }
}

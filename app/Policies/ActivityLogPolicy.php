<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class ActivityLogPolicy
{
    /**
     * The audit trail is an administrative report. Managers read quality data,
     * not who changed which account or session.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->role === Role::Admin;
    }
}

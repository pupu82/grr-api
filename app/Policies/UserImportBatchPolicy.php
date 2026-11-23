<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserImportBatch;

class UserImportBatchPolicy
{
    /**
     * A preview belongs to the admin who uploaded it. Another admin must upload
     * and confirm their own file instead of applying someone else's preview.
     */
    public function confirm(User $actor, UserImportBatch $batch): bool
    {
        return $actor->role === Role::Admin && $batch->actor_id === $actor->id;
    }
}

<?php

namespace App\Policies\Concerns;

use App\Enums\Role;
use App\Models\User;

/**
 * Shared authorization for master data tables: admins manage, managers read.
 * Inspectors have no access; sessions serve them the data they need.
 */
trait ManagesMasterData
{
    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, mixed $record): bool
    {
        return $this->canRead($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->role === Role::Admin;
    }

    public function update(User $actor, mixed $record): bool
    {
        return $actor->role === Role::Admin;
    }

    public function delete(User $actor, mixed $record): bool
    {
        return $actor->role === Role::Admin;
    }

    private function canRead(User $actor): bool
    {
        return in_array($actor->role, [Role::Admin, Role::Manager], strict: true);
    }
}

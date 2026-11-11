<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    /**
     * Only an admin can list accounts.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->role === Role::Admin;
    }

    /**
     * An admin can view any account; every user can view their own.
     */
    public function view(User $actor, User $target): bool
    {
        return $actor->role === Role::Admin || $actor->is($target);
    }

    /**
     * Only an admin can create accounts.
     */
    public function create(User $actor): bool
    {
        return $actor->role === Role::Admin;
    }

    /**
     * Only an admin can change another account; a user may update their own.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->role === Role::Admin || $actor->is($target);
    }

    /**
     * Approve pending accounts: admins only, and never their own.
     */
    public function approve(User $actor, User $target): bool
    {
        return $actor->role === Role::Admin && ! $actor->is($target);
    }

    /**
     * Replace another account's forgotten password. Never your own: a signed-in
     * user changes their own password by proving the current one.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->role === Role::Admin && ! $actor->is($target);
    }

    /**
     * Deactivate an account, but never your own and never the last active admin.
     */
    public function deactivate(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    /**
     * Delete an account, but never your own and never the last active admin.
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    private function canManage(User $actor, User $target): bool
    {
        return $actor->role === Role::Admin
            && ! $actor->is($target)
            && ! $this->isLastActiveAdmin($target);
    }

    private function isLastActiveAdmin(User $target): bool
    {
        if ($target->role !== Role::Admin || $target->status !== UserStatus::Active) {
            return false;
        }

        return ! User::query()
            ->whereKeyNot($target->getKey())
            ->where('role', Role::Admin->value)
            ->where('status', UserStatus::Active->value)
            ->exists();
    }
}

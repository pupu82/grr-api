<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\InspectionSession;
use App\Models\User;

class InspectionSessionPolicy
{
    /**
     * Admins and managers see every session; an inspector only sees the ones
     * they participate in, which the controller applies as a scope.
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, InspectionSession $session): bool
    {
        if ($actor->role !== Role::Inspector) {
            return true;
        }

        return $session->participants()
            ->where('user_id', $actor->id)
            ->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->role === Role::Admin;
    }

    public function start(User $actor, InspectionSession $session): bool
    {
        return $actor->role === Role::Admin;
    }

    /**
     * Only the inspector registered for this session runs it, and only their
     * own run. An admin cannot answer on someone's behalf.
     */
    public function startRun(User $actor, InspectionSession $session): bool
    {
        return $this->isParticipant($actor, $session);
    }

    public function viewCurrentSample(User $actor, InspectionSession $session): bool
    {
        return $this->isParticipant($actor, $session);
    }

    public function submitResult(User $actor, InspectionSession $session): bool
    {
        return $this->isParticipant($actor, $session);
    }

    /**
     * The escape hatch for an inspector who will never finish.
     */
    public function close(User $actor, InspectionSession $session): bool
    {
        return $actor->role === Role::Admin;
    }

    public function delete(User $actor, InspectionSession $session): bool
    {
        return $actor->role === Role::Admin;
    }

    private function isParticipant(User $actor, InspectionSession $session): bool
    {
        return $actor->role === Role::Inspector
            && $session->participants()
                ->where('user_id', $actor->id)
                ->exists();
    }
}

<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApproveUser
{
    /**
     * Activate a pending account, assign its team and record who approved it.
     * The team carries the plant, so assigning the team also assigns the plant.
     */
    public function handle(User $target, Team $team, User $actor): User
    {
        if ($target->status !== UserStatus::Pending) {
            throw ValidationException::withMessages([
                'user' => 'Only pending accounts can be approved.',
            ]);
        }

        $target->status = UserStatus::Active;
        $target->team_id = $team->id;
        $target->approved_by = $actor->id;
        $target->approved_at = now();
        $target->save();

        return $target;
    }
}

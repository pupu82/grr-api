<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReactivateUser
{
    /**
     * Return an inactive account to active. The original approval trail is kept.
     * A team is only required when the account has none, because an inspector
     * without a team belongs to no plant and cannot join a session.
     */
    public function handle(User $target, ?Team $team): User
    {
        if ($target->status !== UserStatus::Inactive) {
            throw ValidationException::withMessages([
                'user' => 'Only inactive accounts can be reactivated.',
            ]);
        }

        if ($team !== null) {
            $target->team_id = $team->id;
        }

        if ($target->team_id === null) {
            throw ValidationException::withMessages([
                'team_id' => 'An account needs a team before it can be reactivated.',
            ]);
        }

        $target->status = UserStatus::Active;
        $target->save();

        // Deactivation already revoked the tokens; clear anything that remains.
        $target->tokens()->delete();

        return $target;
    }
}

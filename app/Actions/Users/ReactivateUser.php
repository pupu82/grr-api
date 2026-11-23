<?php

namespace App\Actions\Users;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReactivateUser
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Return an inactive account to active. The original approval trail is kept.
     * A team is only required when the account has none, because an inspector
     * without a team belongs to no plant and cannot join a session.
     */
    public function handle(User $target, ?Team $team, User $actor): User
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

        return DB::transaction(function () use ($target, $actor): User {
            $target->status = UserStatus::Active;
            $target->save();

            // Deactivation already revoked the tokens; clear anything that remains.
            $target->tokens()->delete();

            $this->logActivity->handle(
                ActivityAction::UserReactivated,
                $actor,
                'user',
                $target->uuid,
                ['employee_code' => $target->employee_code, 'team_id' => $target->team_id],
            );

            return $target;
        });
    }
}

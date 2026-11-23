<?php

namespace App\Actions\Users;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveUser
{
    public function __construct(private LogActivity $logActivity) {}

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

        return DB::transaction(function () use ($target, $team, $actor): User {
            $target->status = UserStatus::Active;
            $target->team_id = $team->id;
            $target->approved_by = $actor->id;
            $target->approved_at = now();
            $target->save();

            $this->logActivity->handle(
                ActivityAction::UserApproved,
                $actor,
                'user',
                $target->uuid,
                ['employee_code' => $target->employee_code, 'team_id' => $team->id],
            );

            return $target;
        });
    }
}

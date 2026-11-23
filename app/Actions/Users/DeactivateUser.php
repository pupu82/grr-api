<?php

namespace App\Actions\Users;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeactivateUser
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Deactivate an account and revoke any token it still holds.
     */
    public function handle(User $target, User $actor): User
    {
        return DB::transaction(function () use ($target, $actor): User {
            $target->status = UserStatus::Inactive;
            $target->save();

            $target->tokens()->delete();

            $this->logActivity->handle(
                ActivityAction::UserDeactivated,
                $actor,
                'user',
                $target->uuid,
                ['employee_code' => $target->employee_code],
            );

            return $target;
        });
    }
}

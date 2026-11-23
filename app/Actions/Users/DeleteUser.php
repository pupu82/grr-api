<?php

namespace App\Actions\Users;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Soft delete an account and revoke any token it still holds.
     */
    public function handle(User $target, User $actor): void
    {
        DB::transaction(function () use ($target, $actor): void {
            $target->tokens()->delete();
            $target->delete();

            $this->logActivity->handle(
                ActivityAction::UserDeleted,
                $actor,
                'user',
                $target->uuid,
                ['employee_code' => $target->employee_code, 'role' => $target->role->value],
            );
        });
    }
}

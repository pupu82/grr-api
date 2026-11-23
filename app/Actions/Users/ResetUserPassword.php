<?php

namespace App\Actions\Users;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetUserPassword
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Replace a forgotten password with a server-generated temporary one and
     * revoke every token the account still holds.
     *
     * The caller decides who may do this and shows the temporary password to
     * the operator exactly once. A self-service password change still requires
     * the current password. A null actor means the server-only break-glass
     * command performed the reset.
     *
     * @param  array<string, mixed>  $context
     */
    public function handle(
        User $target,
        string $temporaryPassword,
        ?User $actor = null,
        array $context = [],
    ): User {
        return DB::transaction(function () use ($target, $temporaryPassword, $actor, $context): User {
            $target->password = $temporaryPassword;
            $target->must_change_password = true;
            $target->save();

            // The old tokens must die with the old password.
            $target->tokens()->delete();

            $this->logActivity->handle(
                ActivityAction::UserPasswordReset,
                $actor,
                'user',
                $target->uuid,
                [...$context, 'employee_code' => $target->employee_code],
            );

            return $target;
        });
    }
}

<?php

namespace App\Console\Commands;

use App\Actions\Users\GenerateTemporaryPassword;
use App\Actions\Users\ResetUserPassword as ResetUserPasswordAction;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('grr:reset-password
    {employee_code : Employee code of the account to recover}
    {--force : Skip the interactive confirmation}')]
#[Description('Reset a forgotten password and print a new temporary password once')]
class ResetUserPassword extends Command
{
    /**
     * Break-glass recovery for an operator with server access, including the
     * case where the last remaining admin is locked out. The API never accepts
     * a password reset without the current password or a second admin.
     */
    public function handle(
        GenerateTemporaryPassword $generateTemporaryPassword,
        ResetUserPasswordAction $resetUserPassword,
    ): int {
        $employeeCode = (string) $this->argument('employee_code');

        $user = User::query()->where('employee_code', $employeeCode)->first();

        if ($user === null) {
            $this->components->error("No account uses employee code [{$employeeCode}].");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Refusing to reset without confirmation. Pass --force for non-interactive use.');

                return self::FAILURE;
            }

            $confirmed = $this->confirm(
                "Reset the password for {$user->employee_code} ({$user->name})?",
            );

            if (! $confirmed) {
                $this->components->warn('Aborted. The password was not changed.');

                return self::FAILURE;
            }
        }

        $temporaryPassword = $generateTemporaryPassword->handle();

        $resetUserPassword->handle($user, $temporaryPassword);

        $this->components->info('Password reset. All existing tokens were revoked.');
        $this->line('Employee code: '.$user->employee_code);
        $this->line('Temporary password: '.$temporaryPassword);
        $this->components->warn('This password is shown once. The account must change it on the next login.');

        return self::SUCCESS;
    }
}

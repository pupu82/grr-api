<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AttemptLogin
{
    /**
     * A bcrypt hash of a random value. Checking the submitted password against
     * it when the employee code is unknown keeps the response time comparable
     * to a wrong password, so the response cannot reveal whether a code exists.
     */
    private const DUMMY_HASH = '$2y$12$nIMu5UaXkioHl69SA9ttY.CiHhu1thVnrErYiylAuf/svWIivIO1.';

    /**
     * Return the matching user only when the code and password are both correct.
     */
    public function handle(string $employeeCode, string $password): ?User
    {
        $user = User::query()
            ->where('employee_code', $employeeCode)
            ->first();

        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}

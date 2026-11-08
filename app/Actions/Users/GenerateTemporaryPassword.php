<?php

namespace App\Actions\Users;

use Illuminate\Support\Str;

class GenerateTemporaryPassword
{
    /**
     * Build a random temporary password for an admin-created account.
     *
     * Symbols are excluded so the password survives being read aloud to the
     * new user or typed by hand on a phone keyboard.
     */
    public function handle(int $length = 12): string
    {
        return Str::password($length, symbols: false);
    }
}

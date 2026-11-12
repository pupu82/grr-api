<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;

class UpdateUser
{
    /**
     * Update the editable profile fields of an account. Only an admin may move
     * an account to another team; a user editing their own profile changes the
     * display name only.
     *
     * @param  array{name?: string, team_id?: int|null}  $attributes
     */
    public function handle(User $actor, User $target, array $attributes): User
    {
        if (array_key_exists('name', $attributes)) {
            $target->name = $attributes['name'];
        }

        if ($actor->role === Role::Admin && array_key_exists('team_id', $attributes)) {
            $target->team_id = $attributes['team_id'];
        }

        $target->save();

        return $target;
    }
}

<?php

namespace App\Actions\MasterData;

use App\Models\Team;

class UpdateTeam
{
    /**
     * @param  array{plant_id?: int, name?: string}  $attributes
     */
    public function handle(Team $team, array $attributes): Team
    {
        $team->update($attributes);

        return $team;
    }
}

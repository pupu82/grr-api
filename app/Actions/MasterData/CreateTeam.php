<?php

namespace App\Actions\MasterData;

use App\Models\Team;

class CreateTeam
{
    /**
     * @param  array{plant_id: int, name: string}  $attributes
     */
    public function handle(array $attributes): Team
    {
        return Team::create($attributes);
    }
}

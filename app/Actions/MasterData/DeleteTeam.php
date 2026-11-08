<?php

namespace App\Actions\MasterData;

use App\Models\Team;

class DeleteTeam
{
    public function handle(Team $team): void
    {
        $team->delete();
    }
}

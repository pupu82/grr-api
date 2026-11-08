<?php

namespace App\Actions\MasterData;

use App\Models\Plant;

class DeletePlant
{
    public function handle(Plant $plant): void
    {
        $plant->delete();
    }
}

<?php

namespace App\Actions\MasterData;

use App\Models\Plant;

class UpdatePlant
{
    /**
     * @param  array{code?: string, name?: string, is_active?: bool}  $attributes
     */
    public function handle(Plant $plant, array $attributes): Plant
    {
        $plant->update($attributes);

        return $plant;
    }
}

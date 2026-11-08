<?php

namespace App\Actions\MasterData;

use App\Models\Plant;

class CreatePlant
{
    /**
     * @param  array{code: string, name: string, is_active?: bool}  $attributes
     */
    public function handle(array $attributes): Plant
    {
        $attributes['is_active'] ??= true;

        return Plant::create($attributes);
    }
}

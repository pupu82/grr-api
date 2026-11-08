<?php

namespace App\Actions\MasterData;

use App\Models\PartType;

class UpdatePartType
{
    /**
     * @param  array{name?: string}  $attributes
     */
    public function handle(PartType $partType, array $attributes): PartType
    {
        $partType->update($attributes);

        return $partType;
    }
}

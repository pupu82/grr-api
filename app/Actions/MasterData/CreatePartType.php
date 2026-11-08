<?php

namespace App\Actions\MasterData;

use App\Models\PartType;

class CreatePartType
{
    /**
     * @param  array{name: string}  $attributes
     */
    public function handle(array $attributes): PartType
    {
        return PartType::create($attributes);
    }
}

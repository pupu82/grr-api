<?php

namespace App\Actions\MasterData;

use App\Models\PartType;

class DeletePartType
{
    public function handle(PartType $partType): void
    {
        $partType->delete();
    }
}

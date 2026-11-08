<?php

namespace App\Actions\MasterData;

use App\Models\Sample;

class DeleteSample
{
    public function handle(Sample $sample): void
    {
        $sample->delete();
    }
}

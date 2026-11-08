<?php

namespace App\Actions\MasterData;

use App\Models\Sample;

class CreateSample
{
    /**
     * @param  array{code: string, part_type_id: int, answer_key: string}  $attributes
     */
    public function handle(array $attributes): Sample
    {
        return Sample::create($attributes);
    }
}

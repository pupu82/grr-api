<?php

namespace App\Models;

use App\Enums\Verdict;
use Database\Factories\SampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'part_type_id',
    'answer_key',
])]
class Sample extends Model
{
    /** @use HasFactory<SampleFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'answer_key' => Verdict::class,
        ];
    }

    public function partType(): BelongsTo
    {
        return $this->belongsTo(PartType::class);
    }

    public function keyHistories(): HasMany
    {
        return $this->hasMany(SampleKeyHistory::class);
    }
}

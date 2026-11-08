<?php

namespace App\Models;

use App\Enums\Verdict;
use Database\Factories\SampleKeyHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sample_id',
    'old_key',
    'new_key',
    'changed_by',
    'changed_at',
])]
class SampleKeyHistory extends Model
{
    /** @use HasFactory<SampleKeyHistoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'old_key' => Verdict::class,
            'new_key' => Verdict::class,
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

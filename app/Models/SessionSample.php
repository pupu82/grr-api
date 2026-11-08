<?php

namespace App\Models;

use App\Enums\Verdict;
use Database\Factories\SessionSampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'session_id',
    'sample_id',
    'answer_key',
    'position',
])]
class SessionSample extends Model
{
    /** @use HasFactory<SessionSampleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'answer_key' => Verdict::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InspectionSession::class, 'session_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class, 'session_sample_id');
    }
}

<?php

namespace App\Models;

use App\Enums\Judgement;
use Database\Factories\GrrEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'participant_id',
    'repeatability',
    'effectiveness',
    'miss_rate',
    'false_alarm_rate',
    'judgement',
    'thresholds',
    'computed_at',
])]
class GrrEvaluation extends Model
{
    /** @use HasFactory<GrrEvaluationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'repeatability' => 'decimal:2',
            'effectiveness' => 'decimal:2',
            'miss_rate' => 'decimal:2',
            'false_alarm_rate' => 'decimal:2',
            'thresholds' => 'array',
            'computed_at' => 'datetime',
            'judgement' => Judgement::class,
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(SessionParticipant::class, 'participant_id');
    }
}

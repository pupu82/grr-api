<?php

namespace App\Models;

use App\Enums\AnswerOutcome;
use App\Enums\Verdict;
use Database\Factories\InspectionResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'participant_id',
    'session_sample_id',
    'outcome',
    'verdict',
    'response_time_ms',
    'answered_at',
])]
class InspectionResult extends Model
{
    /** @use HasFactory<InspectionResultFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'outcome' => AnswerOutcome::class,
            'verdict' => Verdict::class,
            'response_time_ms' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(SessionParticipant::class, 'participant_id');
    }

    public function sessionSample(): BelongsTo
    {
        return $this->belongsTo(SessionSample::class, 'session_sample_id');
    }
}

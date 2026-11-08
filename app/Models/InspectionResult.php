<?php

namespace App\Models;

use App\Enums\Verdict;
use Database\Factories\InspectionResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'participant_id',
    'session_sample_id',
    'trial_number',
    'verdict',
])]
class InspectionResult extends Model
{
    /** @use HasFactory<InspectionResultFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'trial_number' => 'integer',
            'verdict' => Verdict::class,
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

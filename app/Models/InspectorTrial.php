<?php

namespace App\Models;

use Database\Factories\InspectorTrialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'participant_id',
    'trial_number',
    'started_at',
    'submitted_at',
])]
class InspectorTrial extends Model
{
    /** @use HasFactory<InspectorTrialFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'trial_number' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(SessionParticipant::class, 'participant_id');
    }
}

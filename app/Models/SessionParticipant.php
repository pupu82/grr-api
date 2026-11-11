<?php

namespace App\Models;

use App\Enums\AnswerOutcome;
use Database\Factories\SessionParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['session_id', 'user_id'])]
class SessionParticipant extends Model
{
    /** @use HasFactory<SessionParticipantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InspectionSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class, 'participant_id');
    }

    /**
     * Missing data, not a verdict. Kept separate so reports never count a
     * timeout as `good` or `defect`.
     */
    public function timedOutResults(): HasMany
    {
        return $this->results()->where('outcome', AnswerOutcome::TimedOut);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(GrrEvaluation::class, 'participant_id');
    }
}

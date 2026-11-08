<?php

namespace App\Models;

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

    public function session(): BelongsTo
    {
        return $this->belongsTo(InspectionSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trials(): HasMany
    {
        return $this->hasMany(InspectorTrial::class, 'participant_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class, 'participant_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(GrrEvaluation::class, 'participant_id');
    }
}

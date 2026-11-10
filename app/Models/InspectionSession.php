<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Database\Factories\InspectionSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'token',
    'plant_id',
    'team_id',
    'part_type_id',
    'session_date',
    'response_time_limit_ms',
])]
class InspectionSession extends Model
{
    /** @use HasFactory<InspectionSessionFactory> */
    use HasFactory, HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'response_time_limit_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => SessionStatus::class,
        ];
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function partType(): BelongsTo
    {
        return $this->belongsTo(PartType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessionSamples(): HasMany
    {
        return $this->hasMany(SessionSample::class, 'session_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class, 'session_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(SessionEvaluation::class, 'session_id');
    }
}

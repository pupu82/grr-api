<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['plant_id', 'name'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, SoftDeletes;

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inspectionSessions(): HasMany
    {
        return $this->hasMany(InspectionSession::class);
    }
}

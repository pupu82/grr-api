<?php

namespace App\Models;

use Database\Factories\PartTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name'])]
class PartType extends Model
{
    /** @use HasFactory<PartTypeFactory> */
    use HasFactory, SoftDeletes;

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }

    public function inspectionSessions(): HasMany
    {
        return $this->hasMany(InspectionSession::class);
    }
}

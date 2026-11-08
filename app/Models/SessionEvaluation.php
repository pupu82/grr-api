<?php

namespace App\Models;

use Database\Factories\SessionEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_id',
    'between_agreement',
    'all_vs_standard',
    'computed_at',
])]
class SessionEvaluation extends Model
{
    /** @use HasFactory<SessionEvaluationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'between_agreement' => 'decimal:2',
            'all_vs_standard' => 'decimal:2',
            'computed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InspectionSession::class, 'session_id');
    }
}

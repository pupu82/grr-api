<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_id',
    'actor_name',
    'action',
    'subject_type',
    'subject_id',
    'context',
    'created_at',
])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The actor may be soft-deleted or absent, for example for a break-glass
     * command run on the server.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

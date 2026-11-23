<?php

namespace App\Models;

use Database\Factories\UserImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's preview of a user spreadsheet, kept until they confirm it.
 *
 * The parsed rows are stored as JSON. No password is ever stored here; the
 * temporary passwords are generated only when the import is committed.
 */
#[Fillable([
    'actor_id',
    'filename',
    'checksum',
    'rows',
    'counts',
    'expires_at',
])]
class UserImportBatch extends Model
{
    /** @use HasFactory<UserImportBatchFactory> */
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
            'rows' => 'array',
            'counts' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

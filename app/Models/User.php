<?php

namespace App\Models;

use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'uuid',
    'employee_code',
    'name',
    'password',
    'role',
    'status',
    'registered_via',
    'must_change_password',
    'team_id',
    'approved_by',
    'approved_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
            'registered_via' => RegistrationSource::class,
            'must_change_password' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Check if the user has the given role.
     */
    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, strict: true);
    }

    /**
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}

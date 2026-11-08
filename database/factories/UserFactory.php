<?php

namespace Database\Factories;

use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'name' => fake()->name(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Inspector,
            'status' => UserStatus::Active,
            'registered_via' => RegistrationSource::SelfRegistered,
            'must_change_password' => false,
            'team_id' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => Role::Admin,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (): array => [
            'role' => Role::Manager,
        ]);
    }

    public function inspector(): static
    {
        return $this->state(fn (): array => [
            'role' => Role::Inspector,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Pending,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Inactive,
        ]);
    }

    public function selfRegistered(): static
    {
        return $this->state(fn (): array => [
            'registered_via' => RegistrationSource::SelfRegistered,
        ]);
    }

    public function createdByAdmin(): static
    {
        return $this->state(fn (): array => [
            'registered_via' => RegistrationSource::Admin,
            'must_change_password' => true,
        ]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => [
            'team_id' => $team->id,
        ]);
    }
}

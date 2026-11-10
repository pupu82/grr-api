<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionSession>
 */
class InspectionSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => strtoupper(fake()->unique()->bothify('SES-####-????')),
            'team_id' => Team::factory(),
            'plant_id' => fn (array $attributes): int => Team::findOrFail($attributes['team_id'])->plant_id,
            'part_type_id' => PartType::factory(),
            'created_by' => User::factory()->admin(),
            'session_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'response_time_limit_ms' => 90000,
            'status' => SessionStatus::Draft,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => [
            'team_id' => $team->id,
            'plant_id' => $team->plant_id,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => SessionStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => SessionStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }
}

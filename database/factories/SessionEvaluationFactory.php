<?php

namespace Database\Factories;

use App\Models\InspectionSession;
use App\Models\SessionEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionEvaluation>
 */
class SessionEvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => InspectionSession::factory(),
            'between_agreement' => fake()->randomFloat(2, 0, 100),
            'all_vs_standard' => fake()->randomFloat(2, 0, 100),
            'computed_at' => now(),
        ];
    }
}

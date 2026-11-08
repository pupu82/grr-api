<?php

namespace Database\Factories;

use App\Enums\Judgement;
use App\Models\GrrEvaluation;
use App\Models\SessionParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrrEvaluation>
 */
class GrrEvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_id' => SessionParticipant::factory(),
            'repeatability' => fake()->randomFloat(2, 0, 100),
            'effectiveness' => fake()->randomFloat(2, 0, 100),
            'miss_rate' => fake()->randomFloat(2, 0, 100),
            'false_alarm_rate' => fake()->randomFloat(2, 0, 100),
            'judgement' => Judgement::Acceptable,
            'thresholds' => [],
            'computed_at' => now(),
        ];
    }
}

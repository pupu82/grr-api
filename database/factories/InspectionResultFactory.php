<?php

namespace Database\Factories;

use App\Enums\Verdict;
use App\Models\InspectionResult;
use App\Models\SessionParticipant;
use App\Models\SessionSample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionResult>
 */
class InspectionResultFactory extends Factory
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
            'session_sample_id' => fn (array $attributes): int => SessionSample::factory()
                ->create([
                    'session_id' => SessionParticipant::findOrFail($attributes['participant_id'])->session_id,
                ])
                ->id,
            'trial_number' => 1,
            'verdict' => fake()->randomElement(Verdict::cases()),
        ];
    }
}

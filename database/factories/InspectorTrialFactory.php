<?php

namespace Database\Factories;

use App\Models\InspectorTrial;
use App\Models\SessionParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectorTrial>
 */
class InspectorTrialFactory extends Factory
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
            'trial_number' => 1,
            'started_at' => now(),
            'submitted_at' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\InspectionSession;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionParticipant>
 */
class SessionParticipantFactory extends Factory
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
            'user_id' => User::factory()->inspector(),
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}

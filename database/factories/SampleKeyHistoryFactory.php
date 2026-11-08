<?php

namespace Database\Factories;

use App\Enums\Verdict;
use App\Models\Sample;
use App\Models\SampleKeyHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleKeyHistory>
 */
class SampleKeyHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sample_id' => Sample::factory(),
            'old_key' => Verdict::Good,
            'new_key' => Verdict::Defect,
            'changed_by' => User::factory()->admin(),
            'changed_at' => now(),
        ];
    }
}

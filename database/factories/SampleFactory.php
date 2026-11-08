<?php

namespace Database\Factories;

use App\Enums\Verdict;
use App\Models\PartType;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sample>
 */
class SampleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SMP-####')),
            'part_type_id' => PartType::factory(),
            'answer_key' => fake()->randomElement(Verdict::cases()),
        ];
    }

    public function good(): static
    {
        return $this->state(fn (): array => [
            'answer_key' => Verdict::Good,
        ]);
    }

    public function defect(): static
    {
        return $this->state(fn (): array => [
            'answer_key' => Verdict::Defect,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\PartType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartType>
 */
class PartTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
        ];
    }
}

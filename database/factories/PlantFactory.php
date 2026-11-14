<?php

namespace Database\Factories;

use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PLT-###')),
            'name' => fake()->randomElement(['Plant A', 'Plant B', 'Plant C', 'Plant D', 'Plant E', 'Plant F', 'Plant G', 'Warehouse A', 'Warehouse B', 'Warehouse C', 'Warehouse D', 'Warehouse E', 'Warehouse F', 'Warehouse G', 'BCP', 'UCP']),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\Verdict;
use App\Models\InspectionSession;
use App\Models\Sample;
use App\Models\SessionSample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionSample>
 */
class SessionSampleFactory extends Factory
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
            'sample_id' => Sample::factory(),
            'part_type_id' => fn (array $attributes): int => Sample::findOrFail($attributes['sample_id'])->part_type_id,
            'part_type_name' => fn (array $attributes): string => Sample::findOrFail($attributes['sample_id'])->partType->name,
            'answer_key' => fn (array $attributes) => Sample::findOrFail($attributes['sample_id'])->answer_key,
            'position' => fn (array $attributes): int => (int) SessionSample::query()
                ->where('session_id', $attributes['session_id'])
                ->max('position') + 1,
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

<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserImportBatch>
 */
class UserImportBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'filename' => 'users.xlsx',
            'checksum' => Str::random(64),
            'rows' => [],
            'counts' => ['create' => 0, 'update' => 0, 'skip' => 0],
            'expires_at' => now()->addHour(),
            'confirmed_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmed_at' => now(),
        ]);
    }
}

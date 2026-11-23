<?php

namespace Database\Factories;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'actor_name' => 'System',
            'action' => ActivityAction::UserCreated,
            'subject_type' => 'user',
            'subject_id' => (string) Str::uuid(),
            'context' => [],
            'created_at' => now(),
        ];
    }

    public function forActor(User $actor): static
    {
        return $this->state(fn (): array => [
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
        ]);
    }

    public function forAction(ActivityAction $action): static
    {
        return $this->state(fn (): array => [
            'action' => $action,
        ]);
    }
}

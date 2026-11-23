<?php

namespace App\Actions\Activity;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * Writes one immutable audit row.
 *
 * Context is caller-supplied but filtered again here: a temporary password or
 * a token must never reach the audit table, even if a future caller passes one
 * by mistake. Keep context small and free of personal data beyond what the
 * event itself already identifies.
 */
class LogActivity
{
    private const SENSITIVE_KEY = '/(password|token|secret|hash|api[_-]?key|authorization|credential)/i';

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(
        ActivityAction $action,
        ?User $actor,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $context = [],
    ): ActivityLog {
        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'System',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context' => $this->sanitize($context),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<mixed, mixed>  $context
     * @return array<mixed, mixed>
     */
    private function sanitize(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key) === 1) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }
}

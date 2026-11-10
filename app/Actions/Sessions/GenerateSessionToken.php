<?php

namespace App\Actions\Sessions;

use App\Models\InspectionSession;

/**
 * Build a human-readable session token such as `SES-2026-0001`.
 *
 * Tokens are unique per year. A concurrent create may pick the same number;
 * the caller retries when the unique constraint rejects it.
 */
class GenerateSessionToken
{
    public function handle(int $year): string
    {
        $prefix = sprintf('SES-%d-', $year);

        $latest = InspectionSession::query()
            ->where('token', 'like', $prefix.'%')
            ->orderByDesc('token')
            ->value('token');

        $next = $latest === null
            ? 1
            : ((int) substr($latest, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

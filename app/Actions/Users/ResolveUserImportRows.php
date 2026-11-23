<?php

namespace App\Actions\Users;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns raw spreadsheet rows into the server's decision for each row.
 *
 * The same action runs for preview and confirmation. Re-running it at confirm
 * time means a team deleted in between, or a code created by another admin, is
 * reflected in the result instead of being applied from a stale preview.
 */
class ResolveUserImportRows
{
    /**
     * @param  list<array{row: int, employee_code: ?string, name: ?string, team_name: ?string, reset_password: ?string}>  $rows
     * @return list<array<string, mixed>>
     */
    public function handle(array $rows): array
    {
        $codes = collect($rows)
            ->map(fn (array $row): string => $this->employeeCode($row))
            ->filter()
            ->values();

        $occurrences = $codes->countBy();

        $teamsByName = Team::query()
            ->whereNull('deleted_at')
            ->get(['id', 'name'])
            ->groupBy(fn (Team $team): string => Str::lower(trim($team->name)));

        $users = User::query()
            ->withTrashed()
            ->whereIn('employee_code', $codes->unique()->all())
            ->get(['id', 'uuid', 'employee_code', 'deleted_at']);

        $activeUsers = $users->whereNull('deleted_at')->keyBy('employee_code');
        $deletedUsers = $users->whereNotNull('deleted_at')->keyBy('employee_code');

        return collect($rows)->map(function (array $row) use ($occurrences, $teamsByName, $activeUsers, $deletedUsers): array {
            $code = $this->employeeCode($row);
            $name = trim((string) ($row['name'] ?? ''));
            $teamName = trim((string) ($row['team_name'] ?? ''));
            $resetPassword = $this->resetPassword($row['reset_password'] ?? null);

            $issues = [];

            if ($code === '') {
                $issues[] = 'employee_code_required';
            } elseif (mb_strlen($code) > 30) {
                $issues[] = 'employee_code_too_long';
            }

            if ($name === '') {
                $issues[] = 'name_required';
            } elseif (mb_strlen($name) > 100) {
                $issues[] = 'name_too_long';
            }

            if ($resetPassword === null) {
                $issues[] = 'reset_password_invalid';
            }

            $teamId = null;
            $resolvedTeamName = $teamName;

            if ($teamName === '') {
                $issues[] = 'team_not_found';
            } else {
                /** @var Collection<int, Team> $matches */
                $matches = $teamsByName->get(Str::lower($teamName), collect());

                if ($matches->isEmpty()) {
                    $issues[] = 'team_not_found';
                } elseif ($matches->count() > 1) {
                    $issues[] = 'team_name_ambiguous';
                } else {
                    $team = $matches->first();
                    $teamId = $team->id;
                    $resolvedTeamName = $team->name;
                }
            }

            if ($code !== '' && $occurrences->get($code, 0) > 1) {
                $issues[] = 'duplicate_in_file';
            }

            /** @var User|null $existingUser */
            $existingUser = $activeUsers->get($code);
            $deletedUser = $deletedUsers->get($code);

            if ($existingUser === null && $deletedUser !== null) {
                $issues[] = 'employee_code_deleted';
            }

            $action = 'skip';

            if ($issues === []) {
                $action = $existingUser === null ? 'create' : 'update';
            }

            return [
                'row' => (int) ($row['row'] ?? 0),
                'employee_code' => $code,
                'name' => $name,
                'team_name' => $resolvedTeamName,
                'reset_password' => $resetPassword ?? false,
                'action' => $action,
                'issues' => $issues,
                'team_id' => $teamId,
                'user_id' => $existingUser?->uuid,
            ];
        })->values()->all();
    }

    /**
     * Employee codes are stored uppercase; every write path normalizes them.
     */
    private function employeeCode(array $row): string
    {
        return Str::upper(trim((string) ($row['employee_code'] ?? '')));
    }

    /**
     * `null` means the cell contains an unsupported value. An empty cell means
     * the password must be left alone.
     */
    private function resetPassword(mixed $value): ?bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = Str::lower(trim((string) $value));

        if ($normalized === '') {
            return false;
        }

        return match ($normalized) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default => null,
        };
    }
}

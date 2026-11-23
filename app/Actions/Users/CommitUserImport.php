<?php

namespace App\Actions\Users;

use App\Actions\Activity\LogActivity;
use App\Enums\ActivityAction;
use App\Enums\Role;
use App\Models\User;
use App\Models\UserImportBatch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies a confirmed preview atomically.
 *
 * Temporary passwords are generated here, never during preview, and are handed
 * back to the caller exactly once in the result array.
 */
class CommitUserImport
{
    public function __construct(
        private readonly ResolveUserImportRows $resolveRows,
        private readonly GenerateTemporaryPassword $generatePassword,
        private readonly CreateUser $createUser,
        private readonly UpdateUser $updateUser,
        private readonly ResetUserPassword $resetPassword,
        private readonly LogActivity $logActivity,
    ) {}

    /**
     * @return array{import_batch_id: string, counts: array<string, int>, results: list<array<string, mixed>>}
     */
    public function handle(User $actor, UserImportBatch $batch): array
    {
        if ($batch->actor_id !== $actor->id) {
            throw new AuthorizationException('Only the admin that created a preview can confirm it.');
        }

        if ($batch->confirmed_at !== null) {
            throw ValidationException::withMessages([
                'import_batch' => 'This import preview has already been confirmed.',
            ]);
        }

        if ($batch->expires_at?->isFuture() !== true) {
            throw ValidationException::withMessages([
                'import_batch' => 'This import preview has expired. Upload the file again.',
            ]);
        }

        $rows = $this->resolveForConfirmation($batch->rows ?? []);

        return DB::transaction(function () use ($actor, $batch, $rows): array {
            $lockedBatch = UserImportBatch::query()
                ->whereKey($batch->id)
                ->whereNull('confirmed_at')
                ->lockForUpdate()
                ->first();

            if ($lockedBatch === null) {
                throw ValidationException::withMessages([
                    'import_batch' => 'This import preview has already been confirmed.',
                ]);
            }

            $results = [];
            $counts = ['created' => 0, 'updated' => 0, 'reset' => 0, 'skipped' => 0];

            foreach ($rows as $row) {
                if ($row['action'] === 'skip') {
                    $counts['skipped']++;
                    $results[] = $this->skippedResult($row);

                    continue;
                }

                $temporaryPassword = null;

                if ($row['action'] === 'create') {
                    $temporaryPassword = $this->generatePassword->handle();

                    $user = $this->createUser->handle($actor, [
                        'employee_code' => $row['employee_code'],
                        'name' => $row['name'],
                        'role' => Role::Inspector,
                        'team_id' => $row['team_id'],
                    ], $temporaryPassword);

                    $resultAction = 'created';
                } else {
                    /** @var User $user */
                    $user = User::query()->where('uuid', $row['user_id'])->firstOrFail();

                    $user = $this->updateUser->handle($actor, $user, [
                        'name' => $row['name'],
                        'team_id' => $row['team_id'],
                    ]);

                    $this->logActivity->handle(
                        ActivityAction::UserUpdated,
                        $actor,
                        'user',
                        $user->uuid,
                        [
                            'employee_code' => $user->employee_code,
                            'import_batch_id' => $batch->uuid,
                        ],
                    );

                    if ($row['reset_password']) {
                        $temporaryPassword = $this->generatePassword->handle();

                        $user = $this->resetPassword->handle(
                            $user,
                            $temporaryPassword,
                            $actor,
                            ['source' => 'import', 'import_batch_id' => $batch->uuid],
                        );

                        $resultAction = 'reset';
                    } else {
                        $resultAction = 'updated';
                    }
                }

                $counts[$resultAction]++;

                $results[] = [
                    'row' => $row['row'],
                    'employee_code' => $user->employee_code,
                    'action' => $resultAction,
                    'issues' => [],
                    'user' => [
                        'id' => $user->uuid,
                        'employee_code' => $user->employee_code,
                        'name' => $user->name,
                    ],
                    'temporary_password' => $temporaryPassword,
                ];
            }

            $lockedBatch->confirmed_at = now();
            $lockedBatch->save();

            $this->logActivity->handle(
                ActivityAction::UserImported,
                $actor,
                null,
                null,
                [
                    'import_batch_id' => $batch->uuid,
                    'filename' => $batch->filename,
                    'counts' => $counts,
                ],
            );

            return [
                'import_batch_id' => $batch->uuid,
                'counts' => $counts,
                'results' => $results,
            ];
        });
    }

    /**
     * Re-check the current database state while preserving validation problems
     * found during preview. A bad cell is normalized for storage, so replaying
     * only the normalized value could otherwise turn a rejected row into a
     * valid one at confirmation time.
     *
     * @param  list<array<string, mixed>>  $previewRows
     * @return list<array<string, mixed>>
     */
    private function resolveForConfirmation(array $previewRows): array
    {
        return collect($this->resolveRows->handle($previewRows))
            ->map(function (array $row, int $index) use ($previewRows): array {
                $issues = array_values(array_unique(array_merge(
                    $previewRows[$index]['issues'] ?? [],
                    $row['issues'],
                )));

                $row['issues'] = $issues;
                $row['action'] = $issues === [] ? $row['action'] : 'skip';

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function skippedResult(array $row): array
    {
        return [
            'row' => $row['row'],
            'employee_code' => $row['employee_code'],
            'action' => 'skipped',
            'issues' => $row['issues'],
            'user' => null,
            'temporary_password' => null,
        ];
    }
}

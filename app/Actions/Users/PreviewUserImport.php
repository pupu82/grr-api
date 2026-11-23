<?php

namespace App\Actions\Users;

use App\Imports\UsersImport;
use App\Models\User;
use App\Models\UserImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\HeadingRowImport;

/**
 * Parses and validates a spreadsheet without changing any account.
 *
 * The parsed decision is kept as an import batch so confirmation needs neither
 * another upload nor a second interpretation of the file.
 */
class PreviewUserImport
{
    public function __construct(
        private readonly Excel $excel,
        private readonly ResolveUserImportRows $resolveRows,
    ) {}

    public function handle(User $actor, UploadedFile $file): UserImportBatch
    {
        $readerType = $this->readerType($file);

        $this->assertHeadings($file, $readerType);

        $maxRows = (int) config('grr.imports.max_rows');

        $import = new UsersImport($maxRows + 1);
        $this->excel->import($import, $file, null, $readerType);

        if (count($import->rows()) > $maxRows) {
            throw ValidationException::withMessages([
                'file' => "The file may contain at most {$maxRows} data rows.",
            ]);
        }

        $rows = $this->resolveRows->handle($import->rows());
        $actions = collect($rows)->countBy(fn (array $row): string => $row['action']);

        return UserImportBatch::create([
            'actor_id' => $actor->id,
            'filename' => $file->getClientOriginalName(),
            'checksum' => hash_file('sha256', $file->getRealPath() ?: $file->getPathname()),
            'rows' => $rows,
            'counts' => [
                'create' => $actions->get('create', 0),
                'update' => $actions->get('update', 0),
                'skip' => $actions->get('skip', 0),
            ],
            'expires_at' => now()->addMinutes((int) config('grr.imports.preview_ttl_minutes')),
        ]);
    }

    private function readerType(UploadedFile $file): string
    {
        return match (Str::lower($file->getClientOriginalExtension())) {
            'xlsx' => Excel::XLSX,
            'csv' => Excel::CSV,
            default => throw ValidationException::withMessages([
                'file' => 'Only xlsx and csv files can be imported.',
            ]),
        };
    }

    private function assertHeadings(UploadedFile $file, string $readerType): void
    {
        $sheets = $this->excel->toArray(new HeadingRowImport, $file, null, $readerType);
        $headings = collect($sheets[0] ?? [])->first();

        $formatted = collect(is_array($headings) ? $headings : [])
            ->map(fn (mixed $heading): string => Str::slug((string) $heading, '_'))
            ->all();

        $missing = array_values(array_diff(['employee_code', 'name', 'team_name'], $formatted));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'The file is missing required columns: '.implode(', ', $missing).'.',
            ]);
        }
    }
}

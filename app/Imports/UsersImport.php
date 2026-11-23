<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Row;
use Stringable;

/**
 * Reads the spreadsheet into raw row values only.
 *
 * Business decisions are deliberately left to ResolveUserImportRows so the
 * same rules can be applied again when the admin confirms the preview.
 */
class UsersImport implements OnEachRow, SkipsEmptyRows, WithHeadingRow, WithLimit
{
    /** @var list<array{row: int, employee_code: ?string, name: ?string, team_name: ?string, reset_password: ?string}> */
    private array $rows = [];

    public function __construct(private readonly int $limit) {}

    public function limit(): int
    {
        return $this->limit;
    }

    public function onRow(Row $row): void
    {
        $this->rows[] = [
            'row' => $row->getIndex(),
            'employee_code' => $this->value($row, 'employee_code'),
            'name' => $this->value($row, 'name'),
            'team_name' => $this->value($row, 'team_name'),
            'reset_password' => $this->value($row, 'reset_password'),
        ];
    }

    /**
     * @return list<array{row: int, employee_code: ?string, name: ?string, team_name: ?string, reset_password: ?string}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    private function value(Row $row, string $key): ?string
    {
        if (! isset($row[$key])) {
            return null;
        }

        $value = $row[$key];

        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return trim((string) $value);
        }

        return null;
    }
}

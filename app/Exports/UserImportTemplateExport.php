<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The empty spreadsheet admins download before preparing a user import.
 *
 * It contains headings only: any sample data row would be imported if the
 * admin forgot to remove it.
 */
class UserImportTemplateExport implements FromCollection, WithHeadings, WithTitle
{
    public function collection(): Collection
    {
        return collect();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'employee_code',
            'name',
            'team_name',
            'reset_password',
        ];
    }

    public function title(): string
    {
        return 'Users';
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Keeps serialized metrics as text so both CSV and XLSX retain two decimals.
 */
class SessionEvaluationValueBinder extends DefaultValueBinder
{
    /**
     * @param  Cell  $cell  Cell to bind the value to
     * @param  mixed  $value  Value to bind in the cell
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && preg_match('/^-?\d+\.\d{2}$/', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING2);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

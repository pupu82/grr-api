<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Employee codes are stored in uppercase, so every write path normalizes the
 * submitted value before validation and persistence.
 */
trait NormalizesEmployeeCode
{
    protected function prepareForValidation(): void
    {
        $employeeCode = $this->input('employee_code');

        if (is_string($employeeCode)) {
            $this->merge(['employee_code' => Str::upper($employeeCode)]);
        }
    }
}

<?php

namespace App\Http\Requests\Concerns;

/**
 * Lets a filter accept either one value (`?plant_id=1`) or several
 * (`?plant_id[]=1&plant_id[]=2`). Single values are wrapped before validation
 * so existing clients keep working and every filter uses one array shape.
 */
trait NormalizesMultiValueFilters
{
    /**
     * @return list<string>
     */
    abstract protected function arrayFilterKeys(): array;

    protected function prepareForValidation(): void
    {
        foreach ($this->arrayFilterKeys() as $key) {
            $value = $this->input($key);

            if ($value !== null && ! is_array($value)) {
                $this->merge([$key => [$value]]);
            }
        }
    }
}

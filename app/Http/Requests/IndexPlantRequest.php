<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesMultiValueFilters;
use Illuminate\Foundation\Http\FormRequest;

class IndexPlantRequest extends FormRequest
{
    use NormalizesMultiValueFilters;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'array', 'min:1', 'max:20'],
            'search.*' => ['string', 'max:100'],
            'code' => ['sometimes', 'array', 'min:1', 'max:50'],
            'code.*' => ['string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function arrayFilterKeys(): array
    {
        return ['search', 'code'];
    }
}

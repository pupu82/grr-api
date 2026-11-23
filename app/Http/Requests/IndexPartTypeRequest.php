<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesMultiValueFilters;
use Illuminate\Foundation\Http\FormRequest;

class IndexPartTypeRequest extends FormRequest
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
            'name' => ['sometimes', 'array', 'min:1', 'max:50'],
            'name.*' => ['string', 'max:100'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function arrayFilterKeys(): array
    {
        return ['search', 'name'];
    }
}

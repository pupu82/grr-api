<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesMultiValueFilters;
use Illuminate\Foundation\Http\FormRequest;

class IndexTeamRequest extends FormRequest
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
            'plant_id' => ['sometimes', 'array', 'min:1', 'max:50'],
            'plant_id.*' => ['integer'],
            'name' => ['sometimes', 'array', 'min:1', 'max:50'],
            'name.*' => ['string', 'max:100'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function arrayFilterKeys(): array
    {
        return ['search', 'plant_id', 'name'];
    }
}

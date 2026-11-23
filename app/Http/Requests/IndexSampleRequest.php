<?php

namespace App\Http\Requests;

use App\Enums\Verdict;
use App\Http\Requests\Concerns\NormalizesMultiValueFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSampleRequest extends FormRequest
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
            'part_type_id' => ['sometimes', 'array', 'min:1', 'max:50'],
            'part_type_id.*' => ['integer'],
            'code' => ['sometimes', 'array', 'min:1', 'max:50'],
            'code.*' => ['string', 'max:30'],
            'answer_key' => ['sometimes', 'array', 'min:1', 'max:2'],
            'answer_key.*' => [Rule::enum(Verdict::class)],
        ];
    }

    /**
     * @return list<string>
     */
    protected function arrayFilterKeys(): array
    {
        return ['search', 'part_type_id', 'code', 'answer_key'];
    }
}

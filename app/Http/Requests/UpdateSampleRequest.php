<?php

namespace App\Http\Requests;

use App\Enums\Verdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSampleRequest extends FormRequest
{
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
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('samples', 'code')
                    ->ignore($this->route('sample'))
                    ->whereNull('deleted_at'),
            ],
            'part_type_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('part_types', 'id')->whereNull('deleted_at'),
            ],
            'answer_key' => ['sometimes', 'required', Rule::enum(Verdict::class)],
        ];
    }
}

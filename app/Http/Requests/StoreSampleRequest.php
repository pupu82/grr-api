<?php

namespace App\Http\Requests;

use App\Enums\Verdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSampleRequest extends FormRequest
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
                'required',
                'string',
                'max:30',
                Rule::unique('samples', 'code')->whereNull('deleted_at'),
            ],
            'part_type_id' => [
                'required',
                'integer',
                Rule::exists('part_types', 'id')->whereNull('deleted_at'),
            ],
            'answer_key' => ['required', Rule::enum(Verdict::class)],
        ];
    }
}

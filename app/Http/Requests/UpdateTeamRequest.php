<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
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
            'plant_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('plants', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }
}

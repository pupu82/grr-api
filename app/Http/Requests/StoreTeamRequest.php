<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('plants', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}

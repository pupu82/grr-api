<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the display name is editable by everyone. An admin may also move an
     * account to another team. Identity, role, status and password are changed
     * through their own dedicated actions and are rejected here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'team_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
            'employee_code' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'password' => ['prohibited'],
            'must_change_password' => ['prohibited'],
            'registered_via' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}

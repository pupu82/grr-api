<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactivateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A team is only required when the account has none: an inspector without a
     * team belongs to no plant and cannot join a session.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_id' => [
                Rule::requiredIf(fn (): bool => $this->route('user')?->team_id === null),
                'nullable',
                'integer',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}

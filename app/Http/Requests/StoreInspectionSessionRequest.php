<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Sample;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInspectionSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Samples may span part types, but the primary part type must be
     * represented. Inspectors are validated against the chosen team.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
            'part_type_id' => [
                'required',
                'integer',
                Rule::exists('part_types', 'id')->whereNull('deleted_at'),
            ],
            'session_date' => ['required', 'date'],
            'response_time_limit_ms' => [
                'sometimes',
                'integer',
                'min:'.config('grr.inspection.response_time_limit_min_ms'),
                'max:'.config('grr.inspection.response_time_limit_max_ms'),
            ],
            'sample_ids' => ['required', 'array', 'min:1'],
            'sample_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('samples', 'id')->whereNull('deleted_at'),
            ],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => [
                'string',
                'distinct',
                Rule::exists('users', 'uuid')
                    ->whereNull('deleted_at')
                    ->where('role', Role::Inspector->value)
                    ->where('status', UserStatus::Active->value)
                    ->where('team_id', $this->input('team_id')),
            ],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $primaryPartTypeId = (int) $this->input('part_type_id');

                $hasPrimarySample = Sample::query()
                    ->whereIn('id', $this->input('sample_ids', []))
                    ->where('part_type_id', $primaryPartTypeId)
                    ->exists();

                if (! $hasPrimarySample) {
                    $validator->errors()->add('sample_ids', 'At least one sample must belong to the primary part type.');
                }
            },
        ];
    }
}

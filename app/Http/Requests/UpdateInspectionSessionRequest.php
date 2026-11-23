<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\InspectionSession;
use App\Models\Sample;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInspectionSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Samples may add or remove part types, but the session's primary part type
     * stays represented. Participants are checked against the session's team.
     * The team and primary part type are immutable.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var InspectionSession $session */
        $session = $this->route('session');

        return [
            'team_id' => ['prohibited'],
            'part_type_id' => ['prohibited'],
            'session_date' => ['sometimes', 'date'],
            'response_time_limit_ms' => [
                'sometimes',
                'integer',
                'min:'.config('grr.inspection.response_time_limit_min_ms'),
                'max:'.config('grr.inspection.response_time_limit_max_ms'),
            ],
            'sample_ids' => ['sometimes', 'array', 'min:1'],
            'sample_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('samples', 'id')->whereNull('deleted_at'),
            ],
            'participant_ids' => ['sometimes', 'array', 'min:1'],
            'participant_ids.*' => [
                'string',
                'distinct',
                Rule::exists('users', 'uuid')
                    ->whereNull('deleted_at')
                    ->where('role', Role::Inspector->value)
                    ->where('status', UserStatus::Active->value)
                    ->where('team_id', $session->team_id),
            ],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        /** @var InspectionSession $session */
        $session = $this->route('session');

        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['session_date', 'response_time_limit_ms', 'sample_ids', 'participant_ids'])) {
                    $validator->errors()->add('session', 'At least one field must be provided.');
                }
            },
            function (Validator $validator) use ($session): void {
                if (! $this->has('sample_ids') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $hasPrimarySample = Sample::query()
                    ->whereIn('id', $this->input('sample_ids', []))
                    ->where('part_type_id', $session->part_type_id)
                    ->exists();

                if (! $hasPrimarySample) {
                    $validator->errors()->add('sample_ids', 'At least one sample must belong to the primary part type.');
                }
            },
        ];
    }
}

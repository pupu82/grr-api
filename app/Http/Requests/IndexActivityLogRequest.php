<?php

namespace App\Http\Requests;

use App\Enums\ActivityAction;
use App\Http\Requests\Concerns\NormalizesMultiValueFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexActivityLogRequest extends FormRequest
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
            'action' => ['sometimes', 'array', 'min:1', 'max:20'],
            'action.*' => [Rule::enum(ActivityAction::class)],
            'actor_id' => ['sometimes', 'array', 'min:1', 'max:50'],
            'actor_id.*' => ['string', 'max:36'],
            'subject_type' => ['sometimes', 'array', 'min:1', 'max:2'],
            'subject_type.*' => [Rule::in(['user', 'session'])],
            'subject_id' => ['sometimes', 'array', 'min:1', 'max:50'],
            'subject_id.*' => ['string', 'max:36'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function arrayFilterKeys(): array
    {
        return ['action', 'actor_id', 'subject_type', 'subject_id'];
    }
}

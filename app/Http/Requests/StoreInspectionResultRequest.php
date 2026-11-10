<?php

namespace App\Http\Requests;

use App\Enums\AnswerOutcome;
use App\Enums\Verdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInspectionResultRequest extends FormRequest
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
     * `response_time_ms` comes from the client stopwatch: timing must work
     * without a connection. The server validates it against the session
     * limit for answered samples.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_sample_id' => ['required', 'integer'],
            'outcome' => ['required', Rule::enum(AnswerOutcome::class)],
            'verdict' => [
                'nullable',
                Rule::enum(Verdict::class),
                Rule::requiredIf(fn (): bool => $this->input('outcome') === AnswerOutcome::Answered->value),
                Rule::prohibitedIf(fn (): bool => $this->input('outcome') === AnswerOutcome::TimedOut->value),
            ],
            'response_time_ms' => [
                'required',
                'integer',
                'min:0',
                'max:'.config('grr.inspection.response_time_limit_max_ms'),
            ],
            'answered_at' => ['required', 'date'],
        ];
    }
}

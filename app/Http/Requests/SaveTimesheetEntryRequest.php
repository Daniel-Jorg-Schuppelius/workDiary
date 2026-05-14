<?php

namespace App\Http\Requests;

use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTimesheetEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'date' => ['nullable', 'date'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'kind' => ['nullable', Rule::in(TimeEntry::KINDS)],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v): void {
            $start = $this->input('started_at');
            $end = $this->input('ended_at');
            $min = $this->input('minutes');
            if (! $start && ! $min) {
                $v->errors()->add('minutes', __('Entweder Start/Ende oder Dauer angeben.'));
            }
            if ($start && ! $end && ! $this->routeIs('*.stopwatch.*')) {
                $v->errors()->add('ended_at', __('Endzeit erforderlich.'));
            }
        });
    }
}

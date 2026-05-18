<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTimesheetEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\TimeEntry;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTimesheetEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Compose `started_at` / `ended_at` from `date` (or parent timesheet's
     * `work_date`) + `start_time` / `end_time`. Day rolls over when
     * `end_time` <= `start_time`.
     */
    protected function prepareForValidation(): void
    {
        $date = is_string($this->input('date')) ? trim($this->input('date')) : null;
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $timesheet = $this->route('timesheet');
            if ($timesheet instanceof Timesheet) {
                $date = $timesheet->work_date->toDateString();
                $this->merge(['date' => $date]);
            }
        }

        $startTime = is_string($this->input('start_time')) ? trim($this->input('start_time')) : null;
        $endTime = is_string($this->input('end_time')) ? trim($this->input('end_time')) : null;

        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        if (! $startTime || ! preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            return;
        }

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $startTime");
        } catch (\Throwable) {
            $start = null;
        }
        if (! $start instanceof CarbonImmutable) {
            return;
        }

        $merge = ['started_at' => $start->format('Y-m-d\TH:i')];

        if ($endTime && preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $endTime");
            } catch (\Throwable) {
                $end = null;
            }
            if ($end instanceof CarbonImmutable) {
                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }
                $merge['ended_at'] = $end->format('Y-m-d\TH:i');
            }
        }

        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
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
            if ($this->filled('end_time') && ! $this->filled('start_time')) {
                $v->errors()->add('start_time', __('Startzeit erforderlich, wenn eine Endzeit angegeben ist.'));
            }
            if ($start && ! $end && ! $this->routeIs('*.stopwatch.*')) {
                $v->errors()->add('end_time', __('Endzeit erforderlich.'));
            }
        });
    }
}

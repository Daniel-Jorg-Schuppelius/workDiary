<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAdminTimeEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\TimeEntry\TimeEntryActivityType;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\{Rule, Validator};

/**
 * Validates a non-project (administrative / travel / training) TimeEntry.
 */
class SaveAdminTimeEntryRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /**
     * Compose `started_at` / `ended_at` from the separately submitted
     * `date` + `start_time` / `end_time` time-only inputs. If the end time
     * is less than or equal to the start time, the day rolls over (the
     * entry crosses midnight).
     */
    protected function prepareForValidation(): void {
        $date = is_string($this->input('date')) ? trim($this->input('date')) : null;
        $startTime = is_string($this->input('start_time')) ? trim($this->input('start_time')) : null;
        $endTime = is_string($this->input('end_time')) ? trim($this->input('end_time')) : null;

        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        if (! $startTime || ! preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            // Allow "no period" — skip composition entirely; do not invent values.
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
    public function rules(): array {
        return [
            'date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'activity_type' => ['required', 'string', Rule::in([
                TimeEntryActivityType::Admin->value,
                TimeEntryActivityType::Travel->value,
                TimeEntryActivityType::Training->value,
                TimeEntryActivityType::Meeting->value,
                TimeEntryActivityType::Internal->value,
                TimeEntryActivityType::Break_->value,
                TimeEntryActivityType::Other->value,
            ])],
            'activity_category_id' => ['nullable', 'integer', Rule::exists('activity_categories', 'id')],
            'attendance_id' => ['nullable', 'integer', Rule::exists('attendances', 'id')],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function ($v): void {
            if ($this->filled('end_time') && ! $this->filled('start_time')) {
                $v->errors()->add('start_time', __('Startzeit erforderlich, wenn eine Endzeit angegeben ist.'));
            }
        });
    }
}

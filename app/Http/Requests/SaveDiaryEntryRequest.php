<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDiaryEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\DiaryEntry;
use App\Models\EntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDiaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // "0" / leerer Wert => null behandeln, damit Folge-Regeln korrekt greifen.
        $typeId = $this->input('entry_type_id');
        if ($typeId === '' || $typeId === '0' || $typeId === 0) {
            $this->merge(['entry_type_id' => null]);
        }
        if ($this->input('tour_id') === '' || $this->input('tour_id') === '0') {
            $this->merge(['tour_id' => null]);
        }
        if ($this->input('customer_id') === '' || $this->input('customer_id') === '0') {
            $this->merge(['customer_id' => null]);
        }
        if ($this->input('assigned_user_id') === '' || $this->input('assigned_user_id') === '0') {
            $this->merge(['assigned_user_id' => null]);
        }
        if ($this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }
        if (is_string($this->input('address_country'))) {
            $this->merge(['address_country' => strtoupper((string) $this->input('address_country'))]);
        }

        // Fallback-Modi: leere Werte werden zu Defaults bzw. null normalisiert.
        if ($this->input('mode') === '' || $this->input('mode') === null) {
            $this->merge(['mode' => DiaryEntry::MODE_FIXED]);
        }
        if ($this->input('location_mode') === '' || $this->input('location_mode') === null) {
            $this->merge(['location_mode' => DiaryEntry::LOCATION_ONSITE]);
        }
        foreach (['due_date', 'window_start_date', 'window_end_date'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->resolveEntryType();
        $requiresCustomer = (bool) ($type?->requires_customer);
        $requiresAddress = (bool) ($type?->requires_address);
        $requiresSchedule = (bool) ($type?->requires_schedule);
        $requiresTour = (bool) ($type?->requires_tour);

        $mode = (string) $this->input('mode', DiaryEntry::MODE_FIXED);
        // 'recurring' wird nur vom Generator vergeben; manuell wählbar sind
        // fixed/deadline/window/backlog.
        $selectableModes = [
            DiaryEntry::MODE_FIXED,
            DiaryEntry::MODE_DEADLINE,
            DiaryEntry::MODE_WINDOW,
            DiaryEntry::MODE_BACKLOG,
            DiaryEntry::MODE_RECURRING,
        ];

        $startRequired = $mode === DiaryEntry::MODE_FIXED;
        $dueRequired = $mode === DiaryEntry::MODE_DEADLINE;
        $windowRequired = $mode === DiaryEntry::MODE_WINDOW;

        return [
            'content' => ['required', 'string', 'max:65535'],
            'response' => ['nullable', 'string', 'max:65535'],
            'status' => ['required', 'integer', 'in:-1,1,2,3'],
            'start_at' => [$startRequired ? 'required' : 'nullable', 'date'],
            'end_at' => [$startRequired ? 'required' : 'nullable', 'date', 'after_or_equal:start_at'],

            'mode' => ['required', Rule::in($selectableModes)],
            'due_date' => [$dueRequired ? 'required' : 'nullable', 'date'],
            'window_start_date' => [$windowRequired ? 'required' : 'nullable', 'date'],
            'window_end_date' => [$windowRequired ? 'required' : 'nullable', 'date', 'after_or_equal:window_start_date'],
            'location_mode' => ['required', Rule::in(DiaryEntry::LOCATION_MODES)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],

            // Phase 6: typgesteuerte Felder
            'entry_type_id' => ['nullable', 'integer', 'exists:entry_types,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'priority' => ['nullable', Rule::in(DiaryEntry::PRIORITIES)],
            'customer_id' => [$requiresCustomer ? 'required' : 'nullable', 'integer', 'exists:customers,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],

            'scheduled_for' => [$requiresSchedule ? 'required' : 'nullable', 'date'],
            'time_window_start' => ['nullable', 'date_format:H:i'],
            'time_window_end' => ['nullable', 'date_format:H:i', 'after_or_equal:time_window_start'],
            'service_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],

            'address_line' => [$requiresAddress ? 'required' : 'nullable', 'string', 'max:200'],
            'address_zip' => ['nullable', 'string', 'max:16'],
            'address_city' => [$requiresAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'address_country' => ['nullable', 'string', 'size:2'],
            'address_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address_lng' => ['nullable', 'numeric', 'between:-180,180'],

            'tour_id' => [$requiresTour ? 'required' : 'nullable', 'integer', 'exists:tours,id'],
            'tour_position' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    private function resolveEntryType(): ?EntryType
    {
        $id = $this->input('entry_type_id');
        if (! $id) {
            return null;
        }

        return EntryType::query()->find((int) $id);
    }
}

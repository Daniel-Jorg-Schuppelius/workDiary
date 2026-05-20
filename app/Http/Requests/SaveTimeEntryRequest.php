<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTimeEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTimeEntryRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        // Range-Modus: Von/Bis sind vorhanden → date/minutes optional, weil
        // der Model-Hook sie aus started_at/ended_at − break_minutes ableitet.
        $isRange = $this->filled('started_at') && $this->filled('ended_at');

        return [
            'date' => [$isRange ? 'nullable' : 'required', 'date'],
            'minutes' => [$isRange ? 'nullable' : 'required', 'integer', 'min:1', 'max:1440'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'diary_entry_id' => ['nullable', 'integer', Rule::exists('diary_entries', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void {
        foreach (['task_id', 'diary_entry_id', 'started_at', 'ended_at'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
        if ($this->input('break_minutes') === '') {
            $this->merge(['break_minutes' => null]);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array {
        return [
            'date' => __('Datum'),
            'minutes' => __('Dauer'),
            'started_at' => __('Von'),
            'ended_at' => __('Bis'),
            'break_minutes' => __('Pause'),
            'description' => __('Beschreibung'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'ended_at.after' => __('„Bis" muss nach „Von" liegen.'),
        ];
    }
}

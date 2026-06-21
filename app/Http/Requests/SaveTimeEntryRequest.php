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

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Support\Tz;
use Illuminate\Validation\Rule;

class SaveTimeEntryRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'task_id' => \App\Models\Task::class,
        'diary_entry_id' => \App\Models\DiaryEntry::class,
    ];

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

        // Die datetime-local-Eingaben (Wanduhrzeit ohne Zeitzone) werden in der
        // aktiven Anzeige-Zeitzone interpretiert und zur Speicherung nach UTC
        // umgerechnet.
        foreach (['started_at', 'ended_at'] as $key) {
            $value = $this->input($key);
            if (is_string($value) && $value !== '') {
                $this->merge([$key => Tz::parse($value)->format('Y-m-d H:i:s')]);
            }
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

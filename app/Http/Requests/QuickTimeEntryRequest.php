<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuickTimeEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Manuelle Buchung aus der Eingabeleiste auf „Heute": ein bekannter Kalendertag
 * plus entweder Dauer (Minuten) oder Von/Bis als H:i-Zeiten (Regel 14).
 */
class QuickTimeEntryRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'project_id' => \App\Models\Project::class,
        'task_id' => \App\Models\Task::class,
        'diary_entry_id' => \App\Models\DiaryEntry::class,
        'tag_ids' => \App\Models\Tag::class,
    ];

    /**
     * `started_at`/`ended_at` aus `date` + `start_time`/`end_time` zusammensetzen.
     * Eingaben werden in der aktiven Anzeige-Zeitzone interpretiert und für die
     * Speicherung nach UTC umgerechnet; liegt die Endzeit vor (oder auf) der
     * Startzeit, läuft der Eintrag über Mitternacht in den Folgetag.
     */
    protected function prepareForValidation(): void {
        foreach (['project_id', 'task_id', 'diary_entry_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
        if ($this->input('break_minutes') === '') {
            $this->merge(['break_minutes' => null]);
        }

        $date = is_string($this->input('date')) ? trim($this->input('date')) : null;
        $startTime = is_string($this->input('start_time')) ? trim($this->input('start_time')) : null;
        $endTime = is_string($this->input('end_time')) ? trim($this->input('end_time')) : null;

        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        if (! $startTime || ! preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            return;
        }

        $tz = Tz::current();
        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $startTime", $tz);
        } catch (\Throwable) {
            $start = null;
        }
        if (! $start instanceof CarbonImmutable) {
            return;
        }

        $merge = ['started_at' => $start->setTimezone('UTC')->format('Y-m-d H:i:s')];

        if ($endTime && preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $endTime", $tz);
            } catch (\Throwable) {
                $end = null;
            }
            if ($end instanceof CarbonImmutable) {
                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }
                $merge['ended_at'] = $end->setTimezone('UTC')->format('Y-m-d H:i:s');
            }
        }

        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        // Range-Modus: Von/Bis vorhanden → minutes optional, der Model-Hook
        // leitet minutes/date aus started_at/ended_at − break_minutes ab.
        $isRange = $this->filled('started_at') && $this->filled('ended_at');

        return [
            'project_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'task_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('tasks')],
            'diary_entry_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('diary_entries')],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'minutes' => [$isRange ? 'nullable' : 'required', 'integer', 'min:1', 'max:1440'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('tags')],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function ($v): void {
            if ($this->filled('end_time') && ! $this->filled('start_time')) {
                $v->errors()->add('start_time', __('Startzeit erforderlich, wenn eine Endzeit angegeben ist.'));
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array {
        return [
            'project_id' => __('Projekt'),
            'task_id' => __('Aufgabe'),
            'diary_entry_id' => __('Auftrag'),
            'date' => __('Datum'),
            'minutes' => __('Dauer'),
            'start_time' => __('Von'),
            'end_time' => __('Bis'),
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

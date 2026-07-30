<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StartStopwatchRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;

/**
 * Startet die Stoppuhr (laufender TimeEntry) aus der Eingabeleiste heraus.
 */
class StartStopwatchRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'project_id' => \App\Models\Project::class,
        'task_id' => \App\Models\Task::class,
        'diary_entry_id' => \App\Models\DiaryEntry::class,
        'timesheet_id' => \App\Models\Timesheet::class,
    ];

    protected function prepareForValidation(): void {
        foreach (['project_id', 'task_id', 'diary_entry_id', 'timesheet_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'project_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'task_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('tasks')],
            'diary_entry_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('diary_entries')],
            'description' => ['nullable', 'string', 'max:500'],
            'timesheet_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('timesheets')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array {
        return [
            'project_id' => __('Projekt'),
            'task_id' => __('Aufgabe'),
            'diary_entry_id' => __('Auftrag'),
            'description' => __('Beschreibung'),
        ];
    }
}

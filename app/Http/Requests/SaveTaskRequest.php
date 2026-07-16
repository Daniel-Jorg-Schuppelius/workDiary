<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTaskRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Project, User};
use Closure;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'milestone_id' => \App\Models\Milestone::class,
        'parent_task_id' => \App\Models\Task::class,
        'assignee_ids' => \App\Models\User::class,
    ];

    protected function prepareForValidation(): void {
        $this->merge([
            'is_global' => $this->boolean('is_global'),
            'billable' => $this->has('billable') ? $this->boolean('billable') : true,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'milestone_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('milestones')],
            'parent_task_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('tasks')],
            'assignee_ids' => ['array'],
            'assignee_ids.*' => [
                'integer',
                new \App\Rules\ExistsInCurrentOrganization(),
                // Bearbeiter müssen dem Auftrag zugeordnet sein (Team-Mitglied oder
                // Einzelmitglied), sofern das Projekt überhaupt Zuordnungen hat.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $project = $this->route('project');
                    if (! $project instanceof Project) {
                        return;
                    }
                    $assignable = $project->assignableUsers();
                    if ($assignable->isEmpty()) {
                        // Kein Team/Mitglied zugeordnet → mindestens die Mandantengrenze der Projekt-Org erzwingen,
                        // sonst wären org-fremde Bearbeiter zuweisbar (Whitebox 2026-07).
                        $inOrg = User::query()
                            ->whereKey((int) $value)
                            ->where('organization_id', $project->organization_id)
                            ->exists();
                        if (! $inOrg) {
                            $fail(__('Ein gewählter Bearbeiter gehört nicht zu dieser Organisation.'));
                        }

                        return;
                    }
                    if (! $assignable->contains('id', (int) $value)) {
                        $fail(__('Ein gewählter Bearbeiter gehört nicht zu einem Team oder den Mitgliedern dieses Auftrags.'));
                    }
                },
            ],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_date' => ['nullable', 'date'],
            'is_global' => ['sometimes', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'internal_rate' => ['nullable', 'numeric', 'min:0'],
            'time_budget' => ['nullable', 'integer', 'min:0'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'budget_type' => ['nullable', Rule::in(['month', 'year'])],
            'billable' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string', 'max:16'],
        ];
    }
}

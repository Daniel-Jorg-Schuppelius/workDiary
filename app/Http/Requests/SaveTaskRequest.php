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
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

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
            'milestone_id' => ['nullable', 'integer', Rule::exists('milestones', 'id')],
            'parent_task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'due_date' => ['nullable', 'date'],
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

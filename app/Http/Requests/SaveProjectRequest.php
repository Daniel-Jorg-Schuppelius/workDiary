<?php

namespace App\Http\Requests;

use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Project|null $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('projects', 'name')->ignore($project?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:16'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_default' => ['sometimes', 'boolean', 'prohibited_unless:parent_id,'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($project): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $parentId = (int) $value;
                    if ($project !== null && $parentId === (int) $project->id) {
                        $fail(__('Ein Projekt kann nicht sein eigenes Übergeordnetes Projekt sein.'));
                        return;
                    }
                    $parent = Project::query()->find($parentId);
                    if ($parent === null) {
                        $fail(__('Übergeordnetes Projekt nicht gefunden.'));
                        return;
                    }
                    if ($project !== null && $project->isAncestorOf($parent)) {
                        $fail(__('Zyklus: das gewählte Übergeordnete Projekt ist ein Sub-Projekt dieses Projekts.'));
                    }
                },
            ],
        ];
    }
}

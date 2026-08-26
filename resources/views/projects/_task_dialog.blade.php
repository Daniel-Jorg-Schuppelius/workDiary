{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _task_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $project, $task (null = neu), $milestones, $parentTasks, $users, $preselectedParentId, $isDialog --}}
@php
    use App\Enums\Task\TaskPriority;
    use App\Enums\Task\TaskStatus;
    /**
     * @var \App\Models\Project $project
     * @var \App\Models\Task|null $task
     * @var \Illuminate\Support\Collection<int, \App\Models\Milestone> $milestones
     * @var \Illuminate\Support\Collection<int, \App\Models\Task> $parentTasks
     * @var \Illuminate\Support\Collection<int, \App\Models\User> $users
     * @var int|null $preselectedParentId
     * @var bool $isDialog
     */
    $isDialog  = $isDialog ?? false;
    $action    = $task
        ? route('projects.tasks.update', [$project, $task])
        : route('projects.tasks.store', $project);
    $dialogUrl = ($task
        ? route('projects.tasks.edit', [$project, $task])
        : route('projects.tasks.create', $project)) . '?dialog=1';

    $statusLabels   = TaskStatus::options();
    $priorityLabels = TaskPriority::options();
@endphp

<x-modal
    :title="$task ? __('Aufgabe bearbeiten') : __('Neue Aufgabe')"
    :eyebrow="__('Aufgabe')"
    icon="task_alt"
    :badge="$task?->priorityLabel()"
    :badge-tone="$task?->priorityTone() ?? 'ghost'"
    tone="primary"
    :action="$action"
    :method="$task ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$task ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Aufgabe')" icon="task_alt" tone="primary">
            <x-input-field name="title"
                           :label="__('Titel')"
                           type="text"
                           value="{{ old('title', $task?->title) }}"
                           required
                           maxlength="200" />

            <x-textarea-field name="description" :label="__('Beschreibung')" rows="3">{{ old('description', $task?->description) }}</x-textarea-field>
        </x-form-group>

        <x-form-group :legend="__('Status & Termin')" icon="traffic" tone="info" cols="2">
            <x-select-field name="status" :label="__('Status')">
                @foreach ($statusLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('status', $task?->status?->value ?? TaskStatus::Open->value) === $val)>{{ $lbl }}</option>
                @endforeach
            </x-select-field>

            <x-select-field name="priority" :label="__('Priorität')">
                @foreach ($priorityLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('priority', $task?->priority?->value ?? TaskPriority::Medium->value) === $val)>{{ $lbl }}</option>
                @endforeach
            </x-select-field>

            <x-date-range
                layout="join"
                :from="old('start_date', $task?->start_date?->format('Y-m-d'))"
                :to="old('due_date', $task?->due_date?->format('Y-m-d'))"
                fromName="start_date"
                toName="due_date"
                :fromLabel="__('Start')"
                :toLabel="__('Fällig')"
                :label="__('Zeitraum (Start – Fällig)')"
                size=""
                formControl
                :fromError="$errors->first('start_date')"
                :toError="$errors->first('due_date')"
            />

            <div class="fieldset md:col-span-2">
                <span class="fieldset-label">{{ __('Bearbeiter') }}</span>
                @php($selectedAssignees = (array) old('assignee_ids', array_map(fn($id) => \App\Support\Sqid::encode(\App\Models\User::class, $id), $assigneeIds ?? [])))
                <x-user-checklist
                    name="assignee_ids"
                    :users="$users"
                    :selected="$selectedAssignees"
                    :placeholder="__('Bearbeiter suchen…')"
                    :empty-text="__('Dem Auftrag ist noch kein Team/Mitglied zugeordnet.')" />
                <p class="text-xs text-muted">{{ __('Mehrfachauswahl möglich – die erste Person gilt als Hauptverantwortliche/r.') }}</p>
                @error('assignee_ids.*')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('Activity')" icon="payments" tone="success" cols="2">
            <x-input-field name="hourly_rate"
                           :label="__('Stundensatz (EUR)')"
                           type="number"
                           value="{{ old('hourly_rate', $task?->hourly_rate) }}"
                           step="0.01"
                           min="0" />
            <x-input-field name="internal_rate"
                           :label="__('Interner Satz (EUR)')"
                           type="number"
                           value="{{ old('internal_rate', $task?->internal_rate) }}"
                           step="0.01"
                           min="0" />
            <x-input-field name="time_budget"
                           :label="__('Zeitbudget (Minuten)')"
                           type="number"
                           value="{{ old('time_budget', $task?->time_budget) }}"
                           step="1"
                           min="0" />
            <x-input-field name="budget"
                           :label="__('Geldbudget (EUR)')"
                           type="number"
                           value="{{ old('budget', $task?->budget) }}"
                           step="0.01"
                           min="0" />
            <x-select-field name="budget_type" :label="__('Budget-Typ')">
                <option value="" @selected(old('budget_type', $task?->budget_type) === null || old('budget_type', $task?->budget_type) === '')>{{ __('Gesamt') }}</option>
                <option value="month" @selected(old('budget_type', $task?->budget_type) === 'month')>{{ __('Pro Monat') }}</option>
                <option value="year" @selected(old('budget_type', $task?->budget_type) === 'year')>{{ __('Pro Jahr') }}</option>
            </x-select-field>
            <div class="fieldset">
                <span class="fieldset-label">{{ __('Abrechenbar') }}</span>
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="billable" value="0">
                    <input type="checkbox" name="billable" value="1" class="checkbox checkbox-sm checkbox-info"
                           @checked(old('billable', $task?->billable ?? true))>
                    <span>{{ __('Diese Aufgabe ist abrechenbar.') }}</span>
                </label>
            </div>
        </x-form-group>

        @if ($milestones->isNotEmpty() || ($parentTasks->isNotEmpty() && (! $task || ! $task->parent_task_id)))
            <x-form-group :legend="__('Verknüpfung')" icon="link" tone="ghost" cols="2">
                @if ($milestones->isNotEmpty())
                    <x-select-field name="milestone_id" :label="__('Milestone')">
                        <option value="">{{ __('Kein Milestone') }}</option>
                        @foreach ($milestones as $ms)
                            <option value="{{ $ms->sqid }}" @selected((string) old('milestone_id', \App\Support\Sqid::encode(\App\Models\Milestone::class, $task?->milestone_id)) === $ms->sqid)>{{ $ms->title }}</option>
                        @endforeach
                    </x-select-field>
                @endif

                @if ($parentTasks->isNotEmpty() && (! $task || ! $task->parent_task_id))
                    <x-select-field name="parent_task_id" :label="__('Übergeordnete Aufgabe')">
                        <option value="">{{ __('Keine') }}</option>
                        @foreach ($parentTasks as $pt)
                            <option value="{{ $pt->sqid }}"
                                @selected((string) old('parent_task_id', \App\Support\Sqid::encode(\App\Models\Task::class, $task?->parent_task_id ?? $preselectedParentId)) === $pt->sqid)>
                                {{ $pt->title }}
                            </option>
                        @endforeach
                    </x-select-field>
                @endif
            </x-form-group>
        @endif

    {{-- Deep-Link zur verknüpften Todoist-Aufgabe (Feature 055, MVP-116) --}}
    @if ($task && ($todoistUrl = \App\Plugins\Todoist\TodoistPlugin::taskUrl($task)) !== null)
        <a href="{{ $todoistUrl }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-sm inline-flex items-center gap-1">
            <x-icon name="open_in_new" class="text-base" />{{ __('todoist.task_link') }}
        </a>
    @endif
</x-modal>

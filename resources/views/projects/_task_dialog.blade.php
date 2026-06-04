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
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Titel') }}</label>
                <input name="title" type="text" required maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('title', $task?->title) }}">
                @error('title')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Beschreibung') }}</label>
                <textarea name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description', $task?->description) }}</textarea>
            </div>
        </x-form-group>

        <x-form-group :legend="__('Status & Termin')" icon="traffic" tone="info" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Status') }}</label>
                <select name="status" class="select select-bordered w-full">
                    @foreach ($statusLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('status', $task?->status?->value ?? TaskStatus::Open->value) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Priorität') }}</label>
                <select name="priority" class="select select-bordered w-full">
                    @foreach ($priorityLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('priority', $task?->priority?->value ?? TaskPriority::Medium->value) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Startdatum') }}</label>
                <input name="start_date" type="date"
                       class="input input-bordered w-full @error('start_date') input-error @enderror"
                       value="{{ old('start_date', $task?->start_date?->format('Y-m-d')) }}">
                @error('start_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Fälligkeitsdatum') }}</label>
                <input name="due_date" type="date"
                       class="input input-bordered w-full @error('due_date') input-error @enderror"
                       value="{{ old('due_date', $task?->due_date?->format('Y-m-d')) }}">
                @error('due_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Bearbeiter') }}</label>
                @php($selectedAssignees = (array) old('assignee_ids', array_map(fn($id) => \App\Support\Sqid::encode(\App\Models\User::class, $id), $assigneeIds ?? [])))
                @if ($users->isEmpty())
                    <p class="text-xs text-base-content/60">{{ __('Dem Auftrag ist noch kein Team/Mitglied zugeordnet.') }}</p>
                @else
                    <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                        @foreach ($users as $u)
                            <label class="label cursor-pointer justify-start gap-2 rounded px-2 hover:bg-base-200">
                                <input type="checkbox" name="assignee_ids[]" value="{{ $u->sqid }}" class="checkbox checkbox-sm"
                                       @checked(in_array($u->sqid, $selectedAssignees, true))>
                                <span class="text-sm">{{ $u->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-base-content/60">{{ __('Mehrfachauswahl möglich – die erste Person gilt als Hauptverantwortliche/r.') }}</p>
                @endif
                @error('assignee_ids.*')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('Activity')" icon="payments" tone="success" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Stundensatz (EUR)') }}</label>
                <input name="hourly_rate" type="number" step="0.01" min="0"
                       class="input input-bordered w-full"
                       value="{{ old('hourly_rate', $task?->hourly_rate) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Interner Satz (EUR)') }}</label>
                <input name="internal_rate" type="number" step="0.01" min="0"
                       class="input input-bordered w-full"
                       value="{{ old('internal_rate', $task?->internal_rate) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Zeitbudget (Minuten)') }}</label>
                <input name="time_budget" type="number" step="1" min="0"
                       class="input input-bordered w-full"
                       value="{{ old('time_budget', $task?->time_budget) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Geldbudget (EUR)') }}</label>
                <input name="budget" type="number" step="0.01" min="0"
                       class="input input-bordered w-full"
                       value="{{ old('budget', $task?->budget) }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Budget-Typ') }}</label>
                <select name="budget_type" class="select select-bordered w-full">
                    <option value="" @selected(old('budget_type', $task?->budget_type) === null || old('budget_type', $task?->budget_type) === '')>{{ __('Gesamt') }}</option>
                    <option value="month" @selected(old('budget_type', $task?->budget_type) === 'month')>{{ __('Pro Monat') }}</option>
                    <option value="year" @selected(old('budget_type', $task?->budget_type) === 'year')>{{ __('Pro Jahr') }}</option>
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Abrechenbar') }}</label>
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
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Milestone') }}</label>
                        <select name="milestone_id" class="select select-bordered w-full">
                            <option value="">{{ __('Kein Milestone') }}</option>
                            @foreach ($milestones as $ms)
                                <option value="{{ $ms->sqid }}" @selected((string) old('milestone_id', \App\Support\Sqid::encode(\App\Models\Milestone::class, $task?->milestone_id)) === $ms->sqid)>{{ $ms->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($parentTasks->isNotEmpty() && (! $task || ! $task->parent_task_id))
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Übergeordnete Aufgabe') }}</label>
                        <select name="parent_task_id" class="select select-bordered w-full">
                            <option value="">{{ __('Keine') }}</option>
                            @foreach ($parentTasks as $pt)
                                <option value="{{ $pt->sqid }}"
                                    @selected((string) old('parent_task_id', \App\Support\Sqid::encode(\App\Models\Task::class, $task?->parent_task_id ?? $preselectedParentId)) === $pt->sqid)>
                                    {{ $pt->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </x-form-group>
        @endif
</x-modal>

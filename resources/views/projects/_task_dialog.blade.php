{{-- Erwartet: $project, $task (null = neu), $milestones, $parentTasks, $users, $preselectedParentId, $isDialog --}}
@php
    use App\Models\Task;
    $isDialog  = $isDialog ?? false;
    $action    = $task
        ? route('projects.tasks.update', [$project, $task])
        : route('projects.tasks.store', $project);
    $dialogUrl = ($task
        ? route('projects.tasks.edit', [$project, $task])
        : route('projects.tasks.create', $project)) . '?dialog=1';

    $statusLabels   = [Task::STATUS_OPEN => __('Offen'), Task::STATUS_IN_PROGRESS => __('In Arbeit'), Task::STATUS_DONE => __('Erledigt')];
    $priorityLabels = [Task::PRIORITY_LOW => __('Niedrig'), Task::PRIORITY_MEDIUM => __('Mittel'), Task::PRIORITY_HIGH => __('Hoch'), Task::PRIORITY_URGENT => __('Dringend')];
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
                        <option value="{{ $val }}" @selected(old('status', $task?->status ?? Task::STATUS_OPEN) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Priorität') }}</label>
                <select name="priority" class="select select-bordered w-full">
                    @foreach ($priorityLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('priority', $task?->priority ?? Task::PRIORITY_MEDIUM) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Fälligkeitsdatum') }}</label>
                <input name="due_date" type="date"
                       class="input input-bordered w-full"
                       value="{{ old('due_date', $task?->due_date?->format('Y-m-d')) }}">
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Zuweisung') }}</label>
                <select name="assigned_to" class="select select-bordered w-full">
                    <option value="">{{ __('Nicht zugewiesen') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('assigned_to', $task?->assigned_to) == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
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
                                <option value="{{ $ms->id }}" @selected(old('milestone_id', $task?->milestone_id) == $ms->id)>{{ $ms->title }}</option>
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
                                <option value="{{ $pt->id }}"
                                    @selected(old('parent_task_id', $task?->parent_task_id ?? $preselectedParentId) == $pt->id)>
                                    {{ $pt->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </x-form-group>
        @endif
</x-modal>

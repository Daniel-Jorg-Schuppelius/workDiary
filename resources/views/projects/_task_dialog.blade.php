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

<x-dialog
    :title="$task ? __('Aufgabe bearbeiten') : __('Neue Aufgabe')"
    :eyebrow="__('Aufgabe')"
    icon="☑"
    :badge="$task?->priorityLabel()"
    :badge-tone="$task?->priorityTone() ?? 'ghost'"
    tone="primary">
    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($task) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Titel') }}</span></div>
            <input name="title" type="text" required maxlength="200"
                   class="input input-bordered w-full"
                   value="{{ old('title', $task?->title) }}">
            @error('title')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </label>

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Beschreibung') }}</span></div>
            <textarea name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description', $task?->description) }}</textarea>
        </label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="form-control">
                <div class="label"><span class="label-text">{{ __('Status') }}</span></div>
                <select name="status" class="select select-bordered">
                    @foreach ($statusLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('status', $task?->status ?? Task::STATUS_OPEN) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <div class="label"><span class="label-text">{{ __('Priorität') }}</span></div>
                <select name="priority" class="select select-bordered">
                    @foreach ($priorityLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('priority', $task?->priority ?? Task::PRIORITY_MEDIUM) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <div class="label"><span class="label-text">{{ __('Fälligkeitsdatum') }}</span></div>
                <input name="due_date" type="date"
                       class="input input-bordered w-full"
                       value="{{ old('due_date', $task?->due_date?->format('Y-m-d')) }}">
            </label>

            <label class="form-control">
                <div class="label"><span class="label-text">{{ __('Zuweisung') }}</span></div>
                <select name="assigned_to" class="select select-bordered">
                    <option value="">{{ __('Nicht zugewiesen') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('assigned_to', $task?->assigned_to) == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </label>

            @if ($milestones->isNotEmpty())
                <label class="form-control">
                    <div class="label"><span class="label-text">{{ __('Milestone') }}</span></div>
                    <select name="milestone_id" class="select select-bordered">
                        <option value="">{{ __('Kein Milestone') }}</option>
                        @foreach ($milestones as $ms)
                            <option value="{{ $ms->id }}" @selected(old('milestone_id', $task?->milestone_id) == $ms->id)>{{ $ms->title }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($parentTasks->isNotEmpty() && (! $task || ! $task->parent_task_id))
                <label class="form-control">
                    <div class="label"><span class="label-text">{{ __('Übergeordnete Aufgabe') }}</span></div>
                    <select name="parent_task_id" class="select select-bordered">
                        <option value="">{{ __('Keine') }}</option>
                        @foreach ($parentTasks as $pt)
                            <option value="{{ $pt->id }}"
                                @selected(old('parent_task_id', $task?->parent_task_id ?? $preselectedParentId) == $pt->id)>
                                {{ $pt->title }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">
                {{ $task ? __('Speichern') : __('Anlegen') }}
            </button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('projects.show', $project) }}#tasks" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>

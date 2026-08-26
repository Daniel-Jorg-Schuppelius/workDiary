{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $task (null|Task), $users, $isDialog --}}
@php
    use App\Enums\Task\TaskPriority;
    use App\Enums\Task\TaskStatus;
    /**
     * @var \App\Models\Task|null $task
     * @var \Illuminate\Support\Collection<int, \App\Models\User> $users
     * @var bool $isDialog
     */
    $isDialog = $isDialog ?? false;
    $action = $task
        ? route('tasks.global.update', $task)
        : route('tasks.global.store');
    $dialogUrl = ($task
        ? route('tasks.global.edit', $task)
        : route('tasks.global.create')) . '?dialog=1';

    $statusLabels = TaskStatus::options();
    $priorityLabels = TaskPriority::options();
@endphp

<x-modal
    :title="$task ? __('Globale Aufgabe bearbeiten') : __('Neue globale Aufgabe')"
    :eyebrow="__('Activity')"
    icon="task_alt"
    tone="primary"
    :action="$action"
    :method="$task ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$task ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif
    <input type="hidden" name="is_global" value="1">

    <x-form-group :legend="__('Aufgabe')" icon="task_alt" tone="primary">
        <x-input-field name="title"
                       :label="__('Titel')"
                       type="text"
                       value="{{ old('title', $task?->title) }}"
                       required
                       maxlength="200" />

        <x-textarea-field name="description" :label="__('Beschreibung')" rows="3">{{ old('description', $task?->description) }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('Status')" icon="traffic" tone="info" cols="2">
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

    {{-- Deep-Link zur verknüpften Todoist-Aufgabe (Feature 055, MVP-116) --}}
    @if ($task && ($todoistUrl = \App\Plugins\Todoist\TodoistPlugin::taskUrl($task)) !== null)
        <a href="{{ $todoistUrl }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-sm inline-flex items-center gap-1">
            <x-icon name="open_in_new" class="text-base" />{{ __('todoist.task_link') }}
        </a>
    @endif
</x-modal>

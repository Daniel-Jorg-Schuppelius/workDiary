{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _task_row.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Partial: einzelne Task-Zeile — erwartet: $task, $indent (bool), $project --}}
<li class="flex flex-wrap items-center gap-2 px-4 py-2.5 {{ $indent ? 'pl-10 bg-base-200/30' : '' }}">
    {{-- Prioritäts-Punkt --}}
    <span class="inline-block h-2 w-2 shrink-0 rounded-full" style="background:{{ $task->priorityColor() }}"
          title="{{ $task->priorityLabel() }}"></span>

    {{-- Status-Checkbox --}}
    @can('update', $task)
        <x-action-form :action="route('projects.tasks.complete', [$project, $task])" method="PATCH" class="flex items-center">
            <button type="submit"
                    class="checkbox checkbox-sm {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'checkbox-success' : '' }}"
                    title="{{ $task->statusLabel() }}"
                    style="appearance:none;width:1rem;height:1rem;border:2px solid currentColor;border-radius:3px;cursor:pointer;
                           {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'background:#4ade80' : '' }}">
            </button>
        </x-action-form>
    @else
        <span class="inline-block h-4 w-4 shrink-0 rounded border-2 {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'bg-success border-success' : 'border-base-300' }}"></span>
    @endcan

    {{-- Titel + Meta --}}
    <div class="min-w-0 flex-1">
        <span class="text-sm {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'line-through text-muted' : '' }}">
            {{ $task->title }}
        </span>
        <div class="mt-0.5 flex flex-wrap gap-2 text-xs text-muted">
            @if ($task->milestone && ! $indent)
                {{-- already shown in group header, skip --}}
            @endif
            @if ($task->relationLoaded('assignees') ? $task->assignees->isNotEmpty() : $task->assignees()->exists())
                <span>{{ $task->assignees->pluck('name')->join(', ') }}</span>
            @endif
            @if ($task->due_date)
                <span class="{{ $task->due_date->isPast() && $task->status !== \App\Enums\Task\TaskStatus::Done ? 'text-error' : '' }}">
                    {{ $task->due_date->fdate() }}
                </span>
            @endif
            @if (! $indent && $task->subTasks->isNotEmpty())
                <span>{{ $task->subTasks->where('status', \App\Enums\Task\TaskStatus::Done)->count() }}/{{ $task->subTasks->count() }} {{ __('Sub') }}</span>
            @endif
            <x-status-badge size="xs" :tone="$task->statusTone()">{{ $task->statusLabel() }}</x-status-badge>
        </div>
    </div>

    {{-- Aktionen --}}
    <div class="flex shrink-0 gap-1">
        @can('update', $task)
            <x-button :href="route('projects.tasks.edit', [$project, $task])"
               data-entry-modal-trigger tone="ghost" size="xs">{{ __('Edit') }}</x-button>
        @endcan
        @can('delete', $task)
            <form method="POST" action="{{ route('projects.tasks.destroy', [$project, $task]) }}"
                  data-confirm-dialog
                  data-confirm-title="{{ __('Aufgabe löschen') }}"
                  data-confirm-message="{{ $task->subTasks->isNotEmpty() ? __('Sub-Aufgaben werden ebenfalls gelöscht.') : '' }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-button tone="ghost" size="xs" class="text-error">{{ __('Del') }}</x-button>
            </form>
        @endcan
    </div>
</li>

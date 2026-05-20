{{-- Partial: einzelne Task-Zeile — erwartet: $task, $indent (bool), $project --}}
<li class="flex flex-wrap items-center gap-2 px-4 py-2.5 {{ $indent ? 'pl-10 bg-base-200/30' : '' }}">
    {{-- Prioritäts-Punkt --}}
    <span class="inline-block h-2 w-2 shrink-0 rounded-full" style="background:{{ $task->priorityColor() }}"
          title="{{ $task->priorityLabel() }}"></span>

    {{-- Status-Checkbox --}}
    @can('update', $task)
        <form method="POST" action="{{ route('projects.tasks.complete', [$project, $task]) }}" class="flex items-center">
            @csrf @method('PATCH')
            <button type="submit"
                    class="checkbox checkbox-sm {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'checkbox-success' : '' }}"
                    title="{{ $task->statusLabel() }}"
                    style="appearance:none;width:1rem;height:1rem;border:2px solid currentColor;border-radius:3px;cursor:pointer;
                           {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'background:#4ade80' : '' }}">
            </button>
        </form>
    @else
        <span class="inline-block h-4 w-4 shrink-0 rounded border-2 {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'bg-success border-success' : 'border-base-300' }}"></span>
    @endcan

    {{-- Titel + Meta --}}
    <div class="min-w-0 flex-1">
        <span class="text-sm {{ $task->status === \App\Enums\Task\TaskStatus::Done ? 'line-through text-base-content/40' : '' }}">
            {{ $task->title }}
        </span>
        <div class="mt-0.5 flex flex-wrap gap-2 text-xs text-base-content/50">
            @if ($task->milestone && ! $indent)
                {{-- already shown in group header, skip --}}
            @endif
            @if ($task->assignee)
                <span>{{ $task->assignee->name }}</span>
            @endif
            @if ($task->due_date)
                <span class="{{ $task->due_date->isPast() && $task->status !== \App\Enums\Task\TaskStatus::Done ? 'text-error' : '' }}">
                    {{ $task->due_date->format('d.m.Y') }}
                </span>
            @endif
            @if (! $indent && $task->subTasks->isNotEmpty())
                <span>{{ $task->subTasks->where('status', \App\Enums\Task\TaskStatus::Done)->count() }}/{{ $task->subTasks->count() }} {{ __('Sub') }}</span>
            @endif
            <span class="badge badge-xs badge-{{ $task->statusTone() }}">{{ $task->statusLabel() }}</span>
        </div>
    </div>

    {{-- Aktionen --}}
    <div class="flex shrink-0 gap-1">
        @can('update', $task)
            <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
               data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Edit') }}</a>
        @endcan
        @can('delete', $task)
            <form method="POST" action="{{ route('projects.tasks.destroy', [$project, $task]) }}"
                  data-confirm-dialog
                  data-confirm-title="{{ __('Aufgabe löschen') }}"
                  data-confirm-message="{{ $task->subTasks->isNotEmpty() ? __('Sub-Aufgaben werden ebenfalls gelöscht.') : '' }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button class="btn btn-xs btn-ghost text-error">{{ __('Del') }}</button>
            </form>
        @endcan
    </div>
</li>

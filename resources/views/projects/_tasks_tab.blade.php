{{-- Tab: Aufgaben — erwartet: $project, $topTasks, $milestones --}}
@php
    use App\Enums\Task\TaskStatus;
    use App\Models\Task;
    /**
     * @var \App\Models\Project $project
     * @var \Illuminate\Support\Collection<int, \App\Models\Task> $topTasks
     * @var \Illuminate\Support\Collection<int, \App\Models\Milestone> $milestones
     */
    // Aktuelle Filter (rückwärtskompatibel zu alten Parametern task_status / task_milestone).
    $statusFilter    = (string) request()->input('status', request()->input('task_status', ''));
    $milestoneFilter = (string) request()->input('milestone', request()->input('task_milestone', ''));

    // Basis-Query (Tab bleibt aktiv, alte Parameter werden entfernt).
    $baseQuery = static fn (array $overrides) => request()->fullUrlWithQuery(array_merge(
        ['tab' => 'tasks', 'task_status' => null, 'task_milestone' => null],
        $overrides,
    ));

    $filtered = $topTasks->when(
        $statusFilter !== '' && in_array($statusFilter, TaskStatus::values()),
        fn ($c) => $c->where('status', $statusFilter)
    )->when(
        $milestoneFilter !== '',
        fn ($c) => $milestoneFilter === 'none'
            ? $c->whereNull('milestone_id')
            : $c->where('milestone_id', (int) $milestoneFilter)
    );

    $grouped = $filtered->groupBy(fn ($t) => $t->milestone_id ?? 0);
    $milestoneMap = $milestones->keyBy('id');
@endphp

<div class="flex flex-col gap-3">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-100 p-3 shadow-xs">
        <div class="flex flex-wrap gap-2">
            {{-- Status-Filter --}}
            <div class="join">
                @php
                    $statusOpts = ['' => __('Alle')] + TaskStatus::options();
                @endphp
                @foreach ($statusOpts as $val => $lbl)
                    <a href="{{ $baseQuery(['status' => $val ?: null, 'milestone' => $milestoneFilter ?: null]) }}"
                       class="join-item btn btn-xs {{ $statusFilter === $val ? 'btn-primary' : 'btn-ghost' }}">{{ $lbl }}</a>
                @endforeach
            </div>
            {{-- Milestone-Filter --}}
            @if ($milestones->isNotEmpty())
                <select onchange="window.location.href=this.value" class="select select-xs select-bordered">
                    <option value="{{ $baseQuery(['milestone' => null, 'status' => $statusFilter ?: null]) }}"
                            {{ $milestoneFilter === '' ? 'selected' : '' }}>{{ __('Alle Milestones') }}</option>
                    <option value="{{ $baseQuery(['milestone' => 'none', 'status' => $statusFilter ?: null]) }}"
                            {{ $milestoneFilter === 'none' ? 'selected' : '' }}>{{ __('Ohne Milestone') }}</option>
                    @foreach ($milestones as $ms)
                        <option value="{{ $baseQuery(['milestone' => $ms->id, 'status' => $statusFilter ?: null]) }}"
                                {{ (string) $milestoneFilter === (string) $ms->id ? 'selected' : '' }}>{{ $ms->title }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        @can('create', Task::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('projects.tasks.create', $project)"
                        show-label>{{ __('Aufgabe') }}</x-icon-btn>
        @endcan
    </div>

    @if ($filtered->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">checklist</span>'
            :title="__('Keine Aufgaben vorhanden.')" />
    @else
        @foreach ($grouped->sortKeys() as $milestoneId => $tasks)
            @php
                $ms = $milestoneId > 0 ? ($milestoneMap->get($milestoneId)) : null;
            @endphp
            <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                <header class="flex items-center gap-2 border-b border-base-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                    @if ($ms)
                        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px]
                                     {{ $ms->is_completed ? 'bg-success/20 text-success' : 'bg-base-300' }}">
                            {{ $ms->is_completed ? '✓' : '○' }}
                        </span>
                        {{ $ms->title }}
                        @if ($ms->due_date)
                            <span class="font-normal normal-case opacity-60">· {{ $ms->due_date->fdate() }}</span>
                        @endif
                    @else
                        {{ __('Ohne Milestone') }}
                    @endif
                </header>
                <ul class="divide-y divide-base-300">
                    @foreach ($tasks->sortBy('position') as $task)
                        @include('projects._task_row', ['task' => $task, 'indent' => false])
                        @foreach ($task->subTasks->sortBy('position') as $sub)
                            @include('projects._task_row', ['task' => $sub, 'indent' => true])
                        @endforeach
                        @can('create', Task::class)
                            @if (! $task->parent_task_id)
                                <li class="px-4 py-1.5 pl-12">
                                    <a href="{{ route('projects.tasks.create', ['project' => $project, 'parent_id' => $task->sqid]) }}"
                                       data-entry-modal-trigger
                                       class="inline-flex items-center gap-1 text-xs text-base-content/40 hover:text-primary">
                                        <x-icon name="add" /> {{ __('Sub-Aufgabe') }}
                                    </a>
                                </li>
                            @endif
                        @endcan
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</div>

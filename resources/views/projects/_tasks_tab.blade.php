{{-- Tab: Aufgaben — erwartet: $project, $topTasks, $milestones --}}
@php
    use App\Models\Task;
    $statusFilter    = request()->get('task_status', '');
    $milestoneFilter = request()->get('task_milestone', '');

    $filtered = $topTasks->when(
        $statusFilter !== '' && in_array($statusFilter, Task::STATUSES),
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
                    $statusOpts = ['' => __('Alle'), Task::STATUS_OPEN => __('Offen'), Task::STATUS_IN_PROGRESS => __('In Arbeit'), Task::STATUS_DONE => __('Erledigt')];
                @endphp
                @foreach ($statusOpts as $val => $lbl)
                    <a href="{{ request()->fullUrlWithQuery(['task_status' => $val, 'task_milestone' => $milestoneFilter]) }}#tasks"
                       class="join-item btn btn-xs {{ $statusFilter === $val ? 'btn-primary' : 'btn-ghost' }}">{{ $lbl }}</a>
                @endforeach
            </div>
            {{-- Milestone-Filter --}}
            @if ($milestones->isNotEmpty())
                <select onchange="window.location.href=this.value" class="select select-xs select-bordered">
                    <option value="{{ request()->fullUrlWithQuery(['task_milestone' => '', 'task_status' => $statusFilter]) }}#tasks"
                            {{ $milestoneFilter === '' ? 'selected' : '' }}>{{ __('Alle Milestones') }}</option>
                    <option value="{{ request()->fullUrlWithQuery(['task_milestone' => 'none', 'task_status' => $statusFilter]) }}#tasks"
                            {{ $milestoneFilter === 'none' ? 'selected' : '' }}>{{ __('Ohne Milestone') }}</option>
                    @foreach ($milestones as $ms)
                        <option value="{{ request()->fullUrlWithQuery(['task_milestone' => $ms->id, 'task_status' => $statusFilter]) }}#tasks"
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
        <div class="rounded-box border border-base-300 bg-base-100 p-8 text-center text-sm text-base-content/60">
            {{ __('Keine Aufgaben vorhanden.') }}
        </div>
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
                            <span class="font-normal normal-case opacity-60">· {{ $ms->due_date->format('d.m.Y') }}</span>
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
                                    <a href="{{ route('projects.tasks.create', ['project' => $project, 'parent_id' => $task->id]) }}"
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

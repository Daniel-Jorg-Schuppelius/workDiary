{{-- Tab: Übersicht — erwartet: $project, $milestones, $taskStats, $totalMinutes, $monthMinutes, $myMinutes, $nextMilestone, $entries --}}
@php
    $openTasks     = (int) ($taskStats->get(\App\Models\Task::STATUS_OPEN) ?? 0);
    $inProgTasks   = (int) ($taskStats->get(\App\Models\Task::STATUS_IN_PROGRESS) ?? 0);
    $doneTasks     = (int) ($taskStats->get(\App\Models\Task::STATUS_DONE) ?? 0);
    $totalTasks    = $openTasks + $inProgTasks + $doneTasks;
    $totalH        = intdiv($totalMinutes, 60);
    $totalM        = $totalMinutes % 60;
    $totalHours    = $totalH . ':' . str_pad((string) $totalM, 2, '0', STR_PAD_LEFT) . ' h';
@endphp

<div class="flex flex-col gap-4">
    {{-- KPI-Kacheln --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi-tile :label="__('Offene Aufgaben')"
                    :value="$openTasks + $inProgTasks"
                    tone="primary" />
        <x-kpi-tile :label="__('Gesamtstunden')"
                    :value="$totalHours"
                    format="text" />
        <x-kpi-tile :label="__('Erledigte Aufgaben')"
                    :value="$doneTasks"
                    tone="success" />
        <x-kpi-tile :label="__('Nächster Milestone')"
                    :value="$nextMilestone ? truncate($nextMilestone->title, 28) : __('—')"
                    :hint="$nextMilestone?->due_date?->format('d.m.Y') ?: ($nextMilestone ? __('kein Datum') : null)"
                    format="text" />
    </div>

    {{-- Milestones --}}
    @if ($milestones->isNotEmpty())
        <x-card padding="p-0">
            <header class="flex items-center justify-between gap-3 border-b border-base-300 px-4 py-3">
                <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Milestones') }}</span>
                @can('create', \App\Models\Milestone::class)
                    <x-icon-btn icon="add"
                                data-entry-modal-trigger
                                :href="route('projects.milestones.create', $project)"
                                show-label>{{ __('Milestone') }}</x-icon-btn>
                @endcan
            </header>
            <ul class="divide-y divide-base-300">
            @foreach ($milestones as $milestone)
                @php
                    $mTotal = $milestone->tasks->count();
                    $mDone  = $milestone->tasks->where('status', \App\Models\Task::STATUS_DONE)->count();
                    $pct    = $mTotal > 0 ? round($mDone / $mTotal * 100) : 0;
                @endphp
                <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs
                                 {{ $milestone->is_completed ? 'bg-success/20 text-success' : 'bg-base-300 text-base-content/50' }}">
                        {{ $milestone->is_completed ? '✓' : '○' }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium {{ $milestone->is_completed ? 'line-through text-base-content/40' : '' }}">
                                {{ $milestone->title }}
                            </span>
                            @if ($milestone->due_date)
                                <span class="text-xs text-base-content/50">{{ $milestone->due_date->format('d.m.Y') }}</span>
                            @endif
                        </div>
                        @if ($mTotal > 0)
                            <div class="mt-1.5 flex items-center gap-2">
                                <progress class="progress progress-primary h-1.5 flex-1" value="{{ $pct }}" max="100"></progress>
                                <span class="shrink-0 text-xs text-base-content/50">{{ $mDone }}/{{ $mTotal }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="flex gap-1">
                        @can('update', $milestone)
                            <a href="{{ route('projects.milestones.edit', [$project, $milestone]) }}"
                               data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Edit') }}</a>
                        @endcan
                        @can('delete', $milestone)
                            <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $milestone]) }}"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Milestone löschen') }}"
                                  data-confirm-message="{{ __('Aufgaben bleiben erhalten, werden aber vom Milestone getrennt.') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-ghost text-error">{{ __('Del') }}</button>
                            </form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
        </x-card>
    @else
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-sm text-base-content/60">{{ __('Noch keine Milestones angelegt.') }}</span>
                @can('create', \App\Models\Milestone::class)
                    <x-icon-btn icon="add"
                                data-entry-modal-trigger
                                :href="route('projects.milestones.create', $project)"
                                show-label>{{ __('Milestone') }}</x-icon-btn>
                @endcan
            </div>
        </x-card>
    @endif

    {{-- Letzte Aufträge --}}
    <x-card padding="p-0">
        <header class="flex items-center justify-between gap-3 border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Letzte Aufträge') }}</span>
            <a href="{{ route('diary.index', ['project' => $project->id]) }}"
               class="text-xs text-primary hover:underline">{{ __('Alle in der Arbeitsliste') }}</a>
        </header>
        @if ($entries->isEmpty())
            <div class="p-4">
                <x-empty-state compact
                               :title="__('Keine Aufträge auf dieses Projekt gebucht')"
                               :message="__('Sobald Aufträge das Projekt als Initialprojekt setzen oder Stunden hier gebucht werden, erscheinen sie hier.')" />
            </div>
        @else
            <ul class="divide-y divide-base-300">
                @foreach ($entries->take(5) as $entry)
                    @php
                        $dateLabel = match ($entry->mode) {
                            \App\Models\DiaryEntry::MODE_DEADLINE => $entry->due_date?->format('d.m.Y'),
                            \App\Models\DiaryEntry::MODE_WINDOW => $entry->window_start_date?->format('d.m.Y'),
                            \App\Models\DiaryEntry::MODE_BACKLOG => __('Backlog'),
                            default => $entry->start_at?->format('d.m.Y H:i'),
                        };
                    @endphp
                    <li class="px-4 py-3">
                        <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="block">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                                @if ($dateLabel)
                                    <span>{{ $dateLabel }}</span>
                                    <span>·</span>
                                @endif
                                <span>{{ $entry->user->name ?? '—' }}</span>
                                @if ($entry->mode && $entry->mode !== \App\Models\DiaryEntry::MODE_FIXED)
                                    <span class="badge badge-xs badge-outline">{{ $entry->modeLabel() }}</span>
                                @endif
                            </div>
                            <div class="line-clamp-2 text-sm">{{ truncate($entry->content, 150) }}</div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>

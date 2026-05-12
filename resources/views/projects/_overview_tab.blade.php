{{-- Tab: Übersicht — erwartet: $project, $milestones, $taskStats, $totalMinutes, $monthMinutes, $myMinutes, $nextMilestone, $entries --}}
@php
    $openTasks     = (int) ($taskStats->get(\App\Models\Task::STATUS_OPEN) ?? 0);
    $inProgTasks   = (int) ($taskStats->get(\App\Models\Task::STATUS_IN_PROGRESS) ?? 0);
    $doneTasks     = (int) ($taskStats->get(\App\Models\Task::STATUS_DONE) ?? 0);
    $totalTasks    = $openTasks + $inProgTasks + $doneTasks;
    $totalH        = intdiv($totalMinutes, 60);
    $totalM        = $totalMinutes % 60;
@endphp

{{-- Stat-Kacheln --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
        <div class="font-['Space_Grotesk'] text-2xl font-bold text-primary">{{ $openTasks + $inProgTasks }}</div>
        <div class="mt-1 text-xs text-base-content/60">{{ __('Offene Aufgaben') }}</div>
    </div>
    <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
        <div class="font-['Space_Grotesk'] text-2xl font-bold">{{ $totalH }}:{{ str_pad($totalM, 2, '0', STR_PAD_LEFT) }}&thinsp;h</div>
        <div class="mt-1 text-xs text-base-content/60">{{ __('Gesamtstunden') }}</div>
    </div>
    <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
        <div class="font-['Space_Grotesk'] text-2xl font-bold">{{ $doneTasks }}</div>
        <div class="mt-1 text-xs text-base-content/60">{{ __('Erledigte Aufgaben') }}</div>
    </div>
    <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
        @if ($nextMilestone)
            <div class="font-['Space_Grotesk'] text-base font-bold">{{ truncate($nextMilestone->title, 30) }}</div>
            <div class="mt-1 text-xs text-base-content/60">
                {{ $nextMilestone->due_date ? $nextMilestone->due_date->format('d.m.Y') : __('kein Datum') }}
            </div>
        @else
            <div class="font-['Space_Grotesk'] text-sm text-base-content/40">{{ __('Kein Milestone') }}</div>
        @endif
        <div class="mt-1 text-xs text-base-content/60">{{ __('Nächster Milestone') }}</div>
    </div>
</div>

{{-- Milestones --}}
@if ($milestones->isNotEmpty())
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Milestones') }}</span>
            @can('create', \App\Models\Milestone::class)
                <a href="{{ route('projects.milestones.create', $project) }}" data-entry-modal-trigger
                   class="btn btn-xs btn-ghost">+ {{ __('Milestone') }}</a>
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
    </div>
@else
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-sm text-base-content/60">{{ __('Noch keine Milestones angelegt.') }}</span>
            @can('create', \App\Models\Milestone::class)
                <a href="{{ route('projects.milestones.create', $project) }}" data-entry-modal-trigger
                   class="btn btn-xs btn-ghost">+ {{ __('Milestone') }}</a>
            @endcan
        </div>
    </div>
@endif

{{-- Letzte Einträge --}}
<div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
    <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Letzte Einträge') }}</span>
        <a href="{{ route('diary.index', ['project' => $project->id]) }}"
           class="text-xs text-primary hover:underline">{{ __('Alle im Tagebuch') }}</a>
    </header>
    <ul class="divide-y divide-base-300">
        @forelse ($entries->take(5) as $entry)
            <li class="px-4 py-3">
                <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="block">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                        <span>{{ optional($entry->start_at)->format('d.m.Y H:i') }}</span>
                        <span>· {{ $entry->user->name ?? '—' }}</span>
                    </div>
                    <div class="line-clamp-2 text-sm">{{ truncate($entry->content, 150) }}</div>
                </a>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-base-content/60">{{ __('Keine Einträge zugeordnet.') }}</li>
        @endforelse
    </ul>
</div>

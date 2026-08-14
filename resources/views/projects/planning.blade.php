{{--
  Created on   : Thu Jun 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : planning.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Projektplanung') . ' – ' . $project->name)
@section('nav-title', __('Projektplanung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="$project->name" :subtitle="__('Zeitstrahl der Aufgaben (Start – Deadline)')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('projects.show', $project)" show-label>{{ __('Zum Auftrag') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @php($t = $timeline)
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <div class="mb-2 flex items-center justify-between text-xs text-base-content/60">
                <span>{{ $t['from']->isoFormat('DD.MM.YYYY') }} – {{ $t['to']->isoFormat('DD.MM.YYYY') }}</span>
                <span class="hidden sm:inline">{{ __('Balken ziehen zum Verschieben · Ränder ziehen zum Verlängern') }}</span>
            </div>

            @if ($t['groups']->isEmpty())
                <x-empty-state compact
                    icon='<span class="material-symbols-outlined" aria-hidden="true">checklist</span>'
                    :title="__('Noch keine Aufgaben für diesen Auftrag.')" />
            @else
                {{-- Wochen-Achse --}}
                <div class="relative ml-44 h-6 border-b border-base-300">
                    @foreach ($t['weeks'] as $w)
                        <div class="absolute top-0 h-full border-l border-base-200 pl-1 text-[10px] text-base-content/50"
                             style="left: {{ $w['offsetPct'] }}%">{{ $w['label'] }}</div>
                    @endforeach
                    @if ($t['todayPct'] !== null)
                        <div class="absolute top-0 h-full border-l-2 border-error/70" style="left: {{ $t['todayPct'] }}%" title="{{ __('Heute') }}"></div>
                    @endif
                </div>

                {{-- Milestone-Marker --}}
                @if ($t['milestones']->isNotEmpty())
                    <div class="relative ml-44 h-5">
                        @foreach ($t['milestones'] as $m)
                            <div class="absolute top-0 -translate-x-1/2 text-[10px] text-warning"
                                 style="left: {{ $m['offsetPct'] }}%" title="{{ $m['milestone']->title }} · {{ \Illuminate\Support\Carbon::parse($m['milestone']->due_date)->fdate() }}">
                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">flag</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Gruppen je Bearbeiter --}}
                <div class="mt-2 space-y-4">
                    @foreach ($t['groups'] as $group)
                        <div>
                            <div class="mb-1 text-xs font-semibold text-base-content/70">{{ $group['label'] }}</div>
                            <div class="space-y-1">
                                @foreach ($group['tasks'] as $row)
                                    @php($overdue = $row['task']->due_date && \Illuminate\Support\Carbon::parse($row['task']->due_date)->isPast() && $row['task']->status?->value !== 'done')
                                    <div class="flex items-center gap-2">
                                        <div class="shrink-0 truncate pr-2 text-xs" style="width: 10.5rem"
                                             title="{{ $row['task']->title }}">{{ $row['task']->title }}</div>
                                        <div class="relative h-5 flex-1 rounded bg-base-200/60" data-track>
                                            @if ($row['dated'])
                                                <div data-bar
                                                     x-data="ganttBar"
                                                     data-offset="{{ $row['startOffsetDays'] }}"
                                                     data-duration="{{ $row['durationDays'] }}"
                                                     data-total="{{ $t['totalDays'] }}"
                                                     data-from-iso="{{ $t['fromIso'] }}"
                                                     data-url="{{ route('projects.tasks.schedule', [$project, $row['task']]) }}"
                                                     data-editable="{{ $row['editable'] ? '1' : '0' }}"
                                                     data-color="{{ $row['task']->color ?: ($overdue ? '#dc2626' : '#3b82f6') }}"
                                                     data-csrf="{{ csrf_token() }}"
                                                     class="group absolute top-0 flex h-5 items-center overflow-visible rounded text-[10px] text-white select-none"
                                                     :class="cursorClass"
                                                     :style="barStyle"
                                                     @pointerdown="startMove($event)"
                                                     :title="label">
                                                    <template x-if="editable">
                                                        <span class="absolute left-0 top-0 h-5 w-1.5 cursor-ew-resize rounded-l bg-black/20 opacity-0 group-hover:opacity-100"
                                                              @pointerdown.stop="startResize($event, 'l')"></span>
                                                    </template>
                                                    <span class="pointer-events-none truncate px-2" x-text="label"></span>
                                                    <template x-if="editable">
                                                        <span class="absolute right-0 top-0 h-5 w-1.5 cursor-ew-resize rounded-r bg-black/20 opacity-0 group-hover:opacity-100"
                                                              @pointerdown.stop="startResize($event, 'r')"></span>
                                                    </template>
                                                </div>
                                            @else
                                                <span class="absolute left-2 top-0 flex h-5 items-center text-[10px] italic text-base-content/50">{{ __('ohne Termin') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection

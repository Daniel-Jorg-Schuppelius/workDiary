{{--
  Created on   : Thu Jun 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : workload.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Team-Auslastung') . ' – ' . $team->name)
@section('nav-title', __('Team-Auslastung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="$team->name" :subtitle="__('Auslastung im Zeitraum') . ' ' . ($range['label'] ?? '')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('teams.show', $team)" show-label>{{ __('Zum Team') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="mb-3 text-xs text-muted">
        {{ $range['from']->isoFormat('DD.MM.YYYY') }} – {{ $range['to']->isoFormat('DD.MM.YYYY') }}
    </div>

    <div class="space-y-4">
        @forelse ($team->members as $member)
            @php($tasks = $byMember[$member->id] ?? collect())
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <h3 class="card-title text-base">
                            {{ $member->name }}
                            @if ((int) $member->id === (int) $team->lead_user_id)
                                <x-status-badge tone="primary" size="xs">{{ __('Teamleiter') }}</x-status-badge>
                            @endif
                        </h3>
                        <span class="text-xs text-muted">{{ trans_choice(':count Aufgabe|:count Aufgaben', $tasks->count(), ['count' => $tasks->count()]) }}</span>
                    </div>

                    @if ($tasks->isEmpty())
                        <x-empty-state compact
                            icon="event_available"
                            :title="__('Keine Aufgaben im Zeitraum.')" />
                    @else
                        <x-table table-sort="client">
                            <x-slot:head>
                                <tr>
                                    <x-table.th sort type="string">{{ __('Auftrag') }}</x-table.th>
                                    <x-table.th sort type="string">{{ __('Aufgabe') }}</x-table.th>
                                    <x-table.th sort type="date">{{ __('Start') }}</x-table.th>
                                    <x-table.th sort type="date">{{ __('Deadline') }}</x-table.th>
                                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                                </tr>
                            </x-slot:head>
                            @foreach ($tasks as $task)
                                @php($overdue = $task->due_date && $task->due_date->isPast() && $task->status?->value !== 'done')
                                <tr>
                                    <td class="text-sm">{{ $task->project?->name ?? '—' }}</td>
                                    <td class="text-sm">{{ $task->title }}</td>
                                    <td class="text-sm" @if ($task->start_date) data-sort-value="{{ $task->start_date->format('Y-m-d') }}" @endif>{{ $task->start_date?->fdate() ?? '—' }}</td>
                                    <td class="text-sm {{ $overdue ? 'text-error font-semibold' : '' }}" @if ($task->due_date) data-sort-value="{{ $task->due_date->format('Y-m-d') }}" @endif>{{ $task->due_date?->fdate() ?? '—' }}</td>
                                    <td><x-status-badge :tone="$task->statusTone()" size="xs">{{ $task->statusLabel() }}</x-status-badge></td>
                                </tr>
                            @endforeach
                        </x-table>
                    @endif
                </div>
            </div>
        @empty
            <x-empty-state framed
                icon="group"
                :title="__('Dieses Team hat noch keine Mitglieder.')" />
        @endforelse
    </div>
</x-page-shell>
@endsection

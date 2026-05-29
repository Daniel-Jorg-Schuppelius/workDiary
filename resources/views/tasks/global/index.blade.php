@extends('layouts.app')
@section('title', __('Globale Aufgaben'))
@section('nav-title', __('Globale Aufgaben'))

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Task> $tasks */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Wiederverwendbare Tätigkeiten ohne Projektbezug.')">
                <x-slot:actions>
                    @can('create', App\Models\Task::class)
                        <x-icon-btn icon="add" tone="primary" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('tasks.global.create').'?dialog=1'"
                                    show-label>{{ __('Neue Aufgabe') }}</x-icon-btn>
                    @endcan
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <x-table :zebra="true">
            <thead class="bg-base-200">
                <tr>
                    <th>{{ __('Titel') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Priorität') }}</th>
                    <th class="text-right">{{ __('Stundensatz') }}</th>
                    <th class="text-right">{{ __('Zeitbudget (min)') }}</th>
                    <th class="text-center">{{ __('Abrechenbar') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tasks as $task)
                    <tr class="hover">
                        <td class="font-semibold">{{ $task->title }}</td>
                        <td><x-status-badge :tone="$task->statusTone()">{{ $task->statusLabel() }}</x-status-badge></td>
                        <td><x-status-badge :tone="$task->priorityTone()">{{ $task->priorityLabel() }}</x-status-badge></td>
                        <td class="text-right tabular-nums">{{ $task->hourly_rate ?? '–' }}</td>
                        <td class="text-right tabular-nums">{{ $task->time_budget ?? '–' }}</td>
                        <td class="text-center">
                            @if ($task->billable)
                                <x-status-badge tone="info">{{ __('Ja') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('Nein') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('update', $task)
                                <x-icon-btn icon="edit" tone="ghost" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('tasks.global.edit', $task).'?dialog=1'" />
                            @endcan
                            @can('delete', $task)
                                <form method="POST" action="{{ route('tasks.global.destroy', $task) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-xs btn-ghost text-error"
                                            data-confirm-dialog
                                            data-confirm-title="{{ __('Aufgabe löschen?') }}"
                                            data-confirm-message="{{ __('Diese globale Aufgabe wird unwiderruflich entfernt.') }}">
                                        <x-icon name="delete" />
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-8 opacity-70">{{ __('Noch keine globalen Aufgaben.') }}</td></tr>
                @endforelse
            </tbody>
        </x-table>

        {{ $tasks->links() }}
    </x-page-shell>
@endsection

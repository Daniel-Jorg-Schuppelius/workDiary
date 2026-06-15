{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Globale Aufgaben'))
@section('nav-title', __('Globale Aufgaben'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Task> $tasks */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Wiederverwendbare Tätigkeiten ohne Projektbezug.')">
        <x-slot:actions>
            @can('create', App\Models\Task::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('tasks.global.create').'?dialog=1'"
                            show-label>{{ __('Neue Aufgabe') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        <x-filter-bar :action="route('tasks.global.index')" :reset="route('tasks.global.index')">
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" :zebra="true" table-sort="server"
                 :route="route('tasks.global.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['q' => $search ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="title">{{ __('Titel') }}</x-table.th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <x-table.th sort="priority">{{ __('Priorität') }}</x-table.th>
                    <x-table.th sort="hourly_rate" align="right">{{ __('Stundensatz') }}</x-table.th>
                    <x-table.th sort="time_budget" align="right">{{ __('Zeitbudget (min)') }}</x-table.th>
                    <x-table.th sort="billable" align="center">{{ __('Abrechenbar') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
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
                    <x-table.empty :colspan="7" :title="__('Noch keine globalen Aufgaben.')" />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$tasks" />
    </x-index-page>
@endsection

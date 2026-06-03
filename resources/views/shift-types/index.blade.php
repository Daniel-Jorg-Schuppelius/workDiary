{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Schichttypen'))
@section('nav-title', __('Schichttypen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Schichttypen für Dienstpläne und Stempelungen verwalten.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('shift-types.create')"
                        show-label>{{ __('Neuer Schichttyp') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('shift-types.index')" method="GET" :reset="route('shift-types.index')">
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" :zebra="true" table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string" default="asc">{{ __('Name') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Kürzel') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Standardzeit') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Verwendet') }}</x-table.th>
                        <th class="w-32 text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                    @forelse ($types as $type)
                        <tr class="hover">
                            <td class="flex items-center gap-2">
                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $type->color }}"></span>
                                {{ $type->name }}
                            </td>
                            <td><span class="font-mono">{{ $type->abbreviation }}</span></td>
                            <td class="whitespace-nowrap">
                                @if ($type->default_start_time && $type->default_end_time)
                                    {{ $type->default_start_time }}–{{ $type->default_end_time }}
                                @else — @endif
                            </td>
                            <td>
                                @if ($type->is_active)
                                    <x-status-badge tone="success" size="sm">{{ __('Aktiv') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="ghost" size="sm">{{ __('Inaktiv') }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-right">{{ $type->scheduled_shifts_count }}</td>
                            <td class="text-right whitespace-nowrap">
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('shift-types.edit', $type)"
                                            :label="__('Bearbeiten')" />
                                <form action="{{ route('shift-types.destroy', $type) }}" method="POST" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">work_history</span>' :colspan="6" :title="__('Keine Einträge')" compact />
                    @endforelse
        </x-table>
    </x-index-page>
@endsection

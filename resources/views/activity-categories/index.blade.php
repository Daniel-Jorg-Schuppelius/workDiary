{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Tätigkeiten'))
@section('nav-title', __('Tätigkeiten'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $categories */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Verwaltet die Kategorien für nicht-projektgebundene Arbeitszeit.')">
        <x-slot:actions>
            @can('create', App\Models\ActivityCategory::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('activity-categories.create').'?dialog=1'"
                            show-label>{{ __('Neue Tätigkeit') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        <x-filter-bar :action="route('activity-categories.index')" method="GET" :reset="route('activity-categories.index')">
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" :zebra="true" table-sort="server"
                 :route="route('activity-categories.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['q' => $search ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="sort_order" align="right" class="w-16">{{ __('#') }}</x-table.th>
                    <x-table.th sort="key">{{ __('Schlüssel') }}</x-table.th>
                    <x-table.th sort="label">{{ __('Bezeichnung') }}</x-table.th>
                    <x-table.th sort="activity_type">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort="counts_as_work" align="center">{{ __('Arbeit') }}</x-table.th>
                    <x-table.th sort="billable_default" align="center">{{ __('Abrechenbar') }}</x-table.th>
                    <x-table.th sort="active">{{ __('Status') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
                @forelse ($categories as $cat)
                    <tr class="hover">
                        <td class="text-right tabular-nums">{{ $cat->sort_order }}</td>
                        <td>
                            <div class="flex items-center gap-2 font-mono text-xs">
                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $cat->color ?? '#999' }}"></span>
                                {{ $cat->key }}
                            </div>
                        </td>
                        <td class="font-semibold">
                            <div class="flex items-center gap-2">
                                @if ($cat->icon)
                                    <x-icon name="{{ $cat->icon }}" class="text-base opacity-70" />
                                @endif
                                <span>{{ $cat->label }}</span>
                            </div>
                            @if ($cat->description)
                                <div class="text-xs text-muted truncate max-w-md">{{ $cat->description }}</div>
                            @endif
                        </td>
                        <td>
                            <x-status-badge tone="ghost">{{ $cat->activity_type->label() }}</x-status-badge>
                        </td>
                        <td class="text-center">
                            @if ($cat->counts_as_work)
                                <x-status-badge tone="success">{{ __('Ja') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('Nein') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($cat->billable_default)
                                <x-status-badge tone="info">{{ __('Ja') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('Nein') }}</x-status-badge>
                            @endif
                        </td>
                        <td>
                            @if ($cat->active)
                                <x-status-badge tone="success">{{ __('Aktiv') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('Inaktiv') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $cat)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('activity-categories.edit', $cat).'?dialog=1'"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $cat)
                                <x-action-form :action="route('activity-categories.destroy', $cat)" method="DELETE"
                                      data-confirm-title="{{ __('Tätigkeit löschen') }}"
                                      :confirm="__('Tätigkeit wird endgültig entfernt. Bestehende Zeiteinträge bleiben erhalten.')"
                                      :confirm-label="__('Löschen')">
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </x-action-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8"
                        icon="category"
                        :title="__('Noch keine Tätigkeiten angelegt')" compact />
                @endforelse
        </x-table>

        <x-pagination :paginator="$categories" standing />
    </x-index-page>
@endsection

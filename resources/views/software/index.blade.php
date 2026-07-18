{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Software'))
@section('nav-title', __('Software'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $softwareItems */
    /** @var array<string, string> $kindOptions */
    /** @var array<string, string> $licenseTypeOptions */
    /** @var array{q: string, kind: string} $activeFilters */
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Software-Katalog (Betriebssysteme, Anwendungen, Lizenzen) verwalten.')">
    <x-slot:actions>
        @if ($canCreate ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('software.create')"
                        show-label>{{ __('Software anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('software.index')" :reset="route('software.index')">
        <input type="text" name="q" value="{{ $activeFilters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        <select name="kind" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Art') }}">
            <option value="">{{ __('Alle Arten') }}</option>
            @foreach ($kindOptions as $value => $label)
                <option value="{{ $value }}" @selected($activeFilters['kind'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($softwareItems->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">apps</span>' />
    @else
        <x-table table-sort="server"
                 :route="route('software.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="array_filter(['q' => $activeFilters['q'], 'kind' => $activeFilters['kind'] === 'all' ? null : $activeFilters['kind']])"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="vendor">{{ __('Hersteller') }}</x-table.th>
                    <x-table.th sort="kind">{{ __('Art') }}</x-table.th>
                    <x-table.th sort="license_type">{{ __('Lizenz') }}</x-table.th>
                    <x-table.th sort="installations_count" align="right">{{ __('Installationen') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($softwareItems as $software)
                <tr class="hover">
                    <td class="font-medium">
                        {{ $software->name }}
                        @if (! $software->is_active)
                            <x-status-badge tone="ghost" size="xs" class="ml-1">{{ __('inaktiv') }}</x-status-badge>
                        @endif
                        @if ($software->default_version)
                            <div class="text-xs text-base-content/60">{{ $software->default_version }}</div>
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ $software->vendor ?? '—' }}</td>
                    <td class="text-base-content/70">{{ $software->kind->label() }}</td>
                    <td class="text-base-content/70">{{ $software->license_type->label() }}</td>
                    <td class="text-right tabular-nums">{{ $software->installations_count }}</td>
                    <td class="text-right">
                        @can('update', $software)
                            <x-icon-btn icon="edit" size="sm"
                                        data-entry-modal-trigger
                                        :href="route('software.edit', $software)" />
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$softwareItems" standing />
    @endif
</x-index-page>
@endsection

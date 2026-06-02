{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Gebäude'))
@section('nav-title', __('Gebäude'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="$site
    ? __('Gebäude am Standort :site.', ['site' => $site->name])
    : __('Gebäude aller Standorte verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('buildings.create')"
                    show-label>{{ __('Gebäude anlegen') }}</x-icon-btn>
    </x-slot:actions>

    @if ($buildings->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">apartment</span>' />
    @else
        <x-filter-bar :action="route('buildings.index')" method="GET" :reset="route('buildings.index')">
            @if ($site)
                <input type="hidden" name="site" value="{{ request()->query('site') }}" />
            @endif
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('buildings.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['site' => request()->query('site'), 'q' => $search ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name">{{ __('Name') }}</x-table.th>
                    <th>{{ __('Standort') }}</th>
                    <x-table.th sort="year_built" align="right">{{ __('Baujahr') }}</x-table.th>
                    <x-table.th sort="gross_area_m2" align="right">{{ __('m²') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($buildings as $building)
                <tr>
                    <td>
                        <a class="link link-hover" href="{{ route('buildings.show', $building) }}">{{ $building->name }}</a>
                        @if ($building->code)
                            <span class="text-base-content/60 ms-1">({{ $building->code }})</span>
                        @endif
                    </td>
                    <td>{{ $building->site?->name }}</td>
                    <td class="text-end">{{ $building->year_built }}</td>
                    <td class="text-end">{{ $building->gross_area_m2 }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="edit" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('buildings.edit', $building)"
                                    :label="__('Bearbeiten')" />
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$buildings" />
    @endif
</x-index-page>
@endsection

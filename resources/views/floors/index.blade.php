{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Geschosse'))
@section('nav-title', __('Geschosse'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="$building
    ? __('Geschosse im Gebäude :building.', ['building' => $building->name])
    : __('Geschosse aller Gebäude verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('floors.create')"
                    show-label>{{ __('Geschoss anlegen') }}</x-icon-btn>
    </x-slot:actions>

    @if ($floors->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">layers</span>' />
    @else
        <x-filter-bar :action="route('floors.index')" method="GET" :reset="route('floors.index')">
            @if ($building)
                <input type="hidden" name="building" value="{{ request()->query('building') }}" />
            @endif
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('floors.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['building' => request()->query('building'), 'q' => $search ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="level" align="right">{{ __('Ebene') }}</x-table.th>
                    <x-table.th sort="label">{{ __('Bezeichnung') }}</x-table.th>
                    <th>{{ __('Gebäude') }}</th>
                    <x-table.th sort="gross_area_m2" align="right">{{ __('m²') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($floors as $floor)
                <tr>
                    <td class="text-end">{{ $floor->level }}</td>
                    <td>
                        <a class="link link-hover" href="{{ route('floors.show', $floor) }}">{{ $floor->label }}</a>
                    </td>
                    <td>{{ $floor->building?->name }}</td>
                    <td class="text-end">{{ $floor->gross_area_m2 }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="edit" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('floors.edit', $floor)"
                                    :label="__('Bearbeiten')" />
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$floors" />
    @endif
</x-index-page>
@endsection

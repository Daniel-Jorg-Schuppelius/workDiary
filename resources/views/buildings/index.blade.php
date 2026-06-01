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
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Standort') }}</th>
                    <th class="text-end">{{ __('Baujahr') }}</th>
                    <th class="text-end">{{ __('m²') }}</th>
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

@extends('layouts.app')

@section('title', __('Geschosse'))
@section('nav-title', __('Geschosse'))

@section('content')
<x-index-page :subtitle="$building
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
        <x-table>
            <x-slot:head>
                <tr>
                    <th class="text-end">{{ __('Ebene') }}</th>
                    <th>{{ __('Bezeichnung') }}</th>
                    <th>{{ __('Gebäude') }}</th>
                    <th class="text-end">{{ __('m²') }}</th>
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
        <div class="mt-4">{{ $floors->links() }}</div>
    @endif
</x-index-page>
@endsection

@extends('layouts.app')

@section('title', __('Standorte'))
@section('nav-title', __('Standorte'))

@section('content')
<x-index-page :subtitle="$customer
    ? __('Standorte für :customer.', ['customer' => $customer->name])
    : __('Standorte aller Kunden verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('sites.create')"
                    show-label>{{ __('Standort anlegen') }}</x-icon-btn>
    </x-slot:actions>

    @if ($sites->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">location_on</span>' />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('Ort') }}</th>
                    <th class="text-end">{{ __('Aktiv') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($sites as $site)
                <tr>
                    <td>
                        <a class="link link-hover" href="{{ route('sites.show', $site) }}">{{ $site->name }}</a>
                        @if ($site->code)
                            <span class="text-base-content/60 ms-1">({{ $site->code }})</span>
                        @endif
                    </td>
                    <td>{{ $site->customer?->name }}</td>
                    <td>{{ trim(($site->address_zip ?? '').' '.($site->address_city ?? '')) }}</td>
                    <td class="text-end">
                        @if ($site->is_active)
                            <x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('inaktiv') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <x-icon-btn icon="edit" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('sites.edit', $site)"
                                    :label="__('Bearbeiten')" />
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $sites->links() }}</div>
    @endif
</x-index-page>
@endsection

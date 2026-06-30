{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Geofences'))
@section('nav-title', __('Geofences'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="$customer
    ? __('Geofences für :customer.', ['customer' => $customer->name])
    : __('Standort-Zonen je Kunde für die automatische Zeiterfassung.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('geofences.create')"
                    show-label>{{ __('Geofence anlegen') }}</x-icon-btn>
    </x-slot:actions>

    @if ($geofences->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">pin_drop</span>' />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Bezeichnung') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('Standort') }}</th>
                    <th class="text-end">{{ __('Radius') }}</th>
                    <th class="text-end">{{ __('Verweildauer') }}</th>
                    <th class="text-end">{{ __('Aktiv') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($geofences as $geofence)
                <tr>
                    <td>{{ $geofence->label }}</td>
                    <td>{{ $geofence->customer?->name }}</td>
                    <td>{{ $geofence->site?->name ?? '—' }}</td>
                    <td class="text-end">{{ $geofence->radius_m }} m</td>
                    <td class="text-end">{{ $geofence->min_dwell_minutes }} min</td>
                    <td class="text-end">
                        @if ($geofence->is_active)
                            <x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('inaktiv') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <x-icon-btn icon="edit" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('geofences.edit', $geofence)"
                                    :label="__('Bearbeiten')" />
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$geofences" standing />
    @endif
</x-index-page>
@endsection

{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wohnungen & Belegung (Branchenprofil Vermieter) — erwartet: $profiles, $tenancies, $reservations, $available, $from, $to --}}
@extends('layouts.app')
@section('title', __('housing.title'))
@section('nav-title', __('housing.title'))
@section('content')
@php($canManage = auth()->user()?->can('create', \App\Models\Rental\RentalCase::class) ?? false)
<x-index-page :subtitle="__('housing.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" show-label data-entry-modal-trigger :href="route('rental.housing.create')">{{ __('housing.create') }}</x-icon-btn>
            <x-icon-btn icon="key" tone="secondary" size="sm" show-label data-entry-modal-trigger :href="route('rental.housing.tenancies.create')">{{ __('housing.tenancy_create') }}</x-icon-btn>
        @endif
    </x-slot:actions>
    <x-validation-errors />
    <x-filter-bar :action="route('rental.housing.index')" :reset="route('rental.housing.index')">
        <input name="q" type="search" value="{{ request('q') }}" class="input input-sm w-48 shrink-0" aria-label="{{ __('Suche') }}" placeholder="{{ __('Suche') }}">
    </x-filter-bar>
    <p class="text-sm text-muted">{{ __('housing.range_hint', ['from' => $from->fdate(), 'to' => $to->fdate()]) }}</p>
    <x-table>
        <x-slot:head><tr>
            <th>{{ __('housing.unit') }}</th><th>{{ __('housing.mode') }}</th><th>{{ __('housing.occupancy') }}</th><th>{{ __('housing.portal') }}</th>
            @if ($canManage)<th class="text-right">{{ __('Aktionen') }}</th>@endif
        </tr></x-slot:head>
        @forelse ($profiles as $profile)
            <tr>
                <td><span class="font-semibold">{{ $profile->asset?->name }}</span><span class="block text-sm text-muted">{{ $profile->asset?->location_text }}</span></td>
                <td>{{ $profile->rental_mode?->label() }}</td>
                <td>
                    <x-status-badge :tone="$available[$profile->id] ? 'success' : 'warning'">{{ $available[$profile->id] ? __('housing.free') : __('housing.unavailable_status') }}</x-status-badge>
                    @foreach ($tenancies->where('asset_id', $profile->asset_id)->where('status', \App\Enums\Rental\RentalTenancyStatus::Active) as $tenancy)
                        <div class="mt-2 text-sm"><x-icon name="key" /> {{ $tenancy->customer?->name }} · {{ $tenancy->starts_on->fdate() }} – {{ $tenancy->ends_on?->fdate() ?? __('housing.open_ended') }}</div>
                    @endforeach
                    @foreach ($reservations->where('asset_id', $profile->asset_id) as $reservation)
                        <div class="mt-2 text-sm">
                            <x-status-badge :tone="$reservation->kind->isBlocking() ? 'warning' : 'info'">{{ $reservation->kind->label() }}</x-status-badge>
                            {{ $reservation->starts_at->fdatetime() }} – {{ $reservation->ends_at->fdatetime() }}
                            @if ($reservation->rentalCase)
                                <a class="link" href="{{ route('rental.show', $reservation->rentalCase) }}">{{ $reservation->rentalCase->customer?->name }} · {{ $reservation->rentalCase->number }}</a>
                            @endif
                        </div>
                    @endforeach
                </td>
                <td>{{ $profile->portal_bookable && $profile->is_rentable ? __('housing.published') : __('housing.hidden') }}</td>
                @if ($canManage)<td class="text-right"><x-icon-btn icon="edit" tone="ghost" :label="__('Bearbeiten')" data-entry-modal-trigger :href="route('rental.housing.edit', $profile)" /></td>@endif
            </tr>
        @empty
            <x-table.empty :colspan="$canManage ? 5 : 4" icon="apartment" :title="__('housing.empty')" :message="__('housing.empty_hint')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$profiles" standing />
    <x-card :title="__('housing.tenancies')" icon="key" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('housing.unit') }}</th><th>{{ __('housing.tenant') }}</th><th>{{ __('Zeitraum') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('housing.rent_amount') }}</th>@if ($canManage)<th class="text-right">{{ __('Aktionen') }}</th>@endif</tr></x-slot:head>
            @forelse ($tenancies as $tenancy)
                <tr>
                    <td>{{ $tenancy->asset?->name }}</td><td>{{ $tenancy->customer?->name }}</td>
                    <td>{{ $tenancy->starts_on->fdate() }} – {{ $tenancy->ends_on?->fdate() ?? __('housing.open_ended') }}</td>
                    <td><x-status-badge :tone="$tenancy->status->tone()">{{ $tenancy->status->label() }}</x-status-badge></td>
                    <td class="text-right tabular-nums">{{ $tenancy->rent_amount?->format() ?? '—' }}
                        @if ($tenancy->ancillary_amount !== null)<span class="block text-xs text-muted">+ {{ $tenancy->ancillary_amount->format() }}</span>@endif
                    </td>
                    @if ($canManage)<td class="text-right"><x-icon-btn icon="edit" tone="ghost" :label="__('Bearbeiten')" data-entry-modal-trigger :href="route('rental.housing.tenancies.edit', $tenancy)" /></td>@endif
                </tr>
            @empty
                <x-table.empty :colspan="$canManage ? 6 : 5" icon="key" :title="__('housing.no_tenancies')" />
            @endforelse
        </x-table>
    </x-card>
    <x-icon-btn icon="mark_email_unread" tone="ghost" size="sm" show-label :href="route('rental.requests.index')">{{ __('housing.holiday_requests') }}</x-icon-btn>
</x-index-page>
@endsection

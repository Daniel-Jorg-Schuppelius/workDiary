{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wohnung mieten im Kundenportal — erwartet: $profiles, $availability, $prices, $direct, $tenancies, $requests --}}
@extends('customer.layout')
@section('title', __('housing.portal_title'))
@section('content')
<x-page-toolbar :title="__('housing.portal_title')" :subtitle="__('housing.portal_subtitle')" class="mb-4" />
<x-validation-errors />
<x-card class="mb-4">
    <form method="GET" action="{{ route('customer.housing.index') }}" class="flex flex-wrap items-end gap-3">
        <x-date-range from-name="arrival" to-name="departure" :from="request('arrival')" :to="request('departure')"
            :from-label="__('housing.arrival')" :to-label="__('housing.departure')" required />
        <x-button type="submit" size="sm">{{ __('Verfügbarkeit prüfen') }}</x-button>
    </form>
</x-card>
<div class="grid gap-4 lg:grid-cols-2">
    @forelse ($profiles as $profile)
        <x-card :title="$profile->asset?->name" icon="apartment">
            <p class="text-sm text-muted">{{ $profile->asset?->location_text }} · {{ $profile->rental_mode?->label() }}</p>
            <p class="mt-2 whitespace-pre-line">{{ $profile->public_description }}</p>
            @if ($profile->rental_mode?->allowsLongTerm())
                <div class="mt-4">
                    <h3 class="font-semibold">{{ \App\Enums\Rental\RentalMode::LongTerm->label() }}</h3>
                    @if ($profile->rent_amount !== null)
                        <p>{{ __('housing.rent_display', ['rent' => $profile->rent_amount->format(), 'costs' => ($profile->ancillary_amount ?? \CommonToolkit\ValueObjects\Money::zero($profile->currency))->format()]) }}</p>
                    @endif
                    @include('customer.housing._request_form', ['mode' => \App\Enums\Rental\RentalMode::LongTerm])
                </div>
            @endif
            @if ($profile->rental_mode?->allowsHoliday())
                <div class="mt-4">
                    <h3 class="font-semibold">{{ \App\Enums\Rental\RentalMode::Holiday->label() }}</h3>
                    <p class="text-sm">{{ __('housing.capacity', ['guests' => $profile->max_guests, 'nights' => $profile->minimum_nights]) }}</p>
                    <p class="text-sm">{{ __('housing.times', ['in' => $profile->check_in_time, 'out' => $profile->check_out_time]) }}</p>
                    @if (array_key_exists($profile->id, $availability))
                        <x-status-badge :tone="$availability[$profile->id] ? 'success' : 'warning'">{{ $availability[$profile->id] ? __('housing.free') : __('housing.unavailable_status') }}</x-status-badge>
                        @if (($prices[$profile->id] ?? null) !== null)
                            <p>{{ __('rental.portal.price_estimate', ['amount' => $prices[$profile->id]->format()]) }}</p>
                        @endif
                    @endif
                    @include('customer.housing._request_form', ['mode' => \App\Enums\Rental\RentalMode::Holiday])
                </div>
            @endif
        </x-card>
    @empty
        <x-empty-state framed icon="apartment" :title="__('housing.portal_empty')" />
    @endforelse
</div>
<x-pagination :paginator="$profiles" standing />
<x-card :title="__('housing.my_tenancies')" icon="key" class="mt-6" padding="p-0">
    <x-table bare>
        <x-slot:head><tr><th>{{ __('housing.unit') }}</th><th>{{ __('Zeitraum') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('housing.rent_amount') }}</th><th class="text-right">{{ __('Aktionen') }}</th></tr></x-slot:head>
        @forelse ($tenancies as $tenancy)
            <tr>
                <td>{{ $tenancy->asset?->name }}</td><td>{{ $tenancy->starts_on->fdate() }} – {{ $tenancy->ends_on?->fdate() ?? __('housing.open_ended') }}</td>
                <td><x-status-badge :tone="$tenancy->status->tone()">{{ $tenancy->status->label() }}</x-status-badge></td>
                <td class="text-right tabular-nums">{{ $tenancy->rent_amount?->format() ?? '—' }}</td>
                <td class="text-right">@if ($tenancy->status === \App\Enums\Rental\RentalTenancyStatus::Requested)
                    <form method="POST" action="{{ route('customer.housing.withdraw', $tenancy) }}">@csrf<x-button type="submit" size="xs" tone="ghost">{{ __('Zurücknehmen') }}</x-button></form>
                @endif</td>
            </tr>
        @empty
            <x-table.empty :colspan="5" icon="key" :title="__('housing.no_tenancies')" />
        @endforelse
    </x-table>
</x-card>
<x-pagination :paginator="$tenancies" standing />
<x-card :title="__('housing.my_holiday_requests')" icon="holiday_village" class="mt-4" padding="p-0">
    <x-table bare>
        <x-slot:head><tr><th>{{ __('housing.unit') }}</th><th>{{ __('Zeitraum') }}</th><th>{{ __('housing.guests') }}</th><th>{{ __('Status') }}</th><th>{{ __('Aktionen') }}</th></tr></x-slot:head>
        @forelse ($requests as $booking)
            <tr>
                <td>{{ $booking->asset?->name }}</td><td>{{ $booking->starts_at->orgTz()->fdate() }} – {{ $booking->ends_at->orgTz()->fdate() }}</td>
                <td>{{ $booking->guests ?? '—' }}</td><td><x-status-badge :tone="$booking->status->tone()">{{ $booking->status->label() }}</x-status-badge>
                    @if ($booking->decline_reason)<p>{{ $booking->decline_reason }}</p>@endif
                </td><td>@if ($booking->isOpen())
                    <form method="POST" action="{{ route('customer.rentals.requests.withdraw', $booking) }}">@csrf<x-button type="submit" size="xs" tone="ghost">{{ __('Zurücknehmen') }}</x-button></form>
                @endif</td>
            </tr>
        @empty
            <x-table.empty :colspan="5" icon="holiday_village" :title="__('housing.no_requests')" />
        @endforelse
    </x-table>
</x-card>
<x-pagination :paginator="$requests" standing />
@endsection

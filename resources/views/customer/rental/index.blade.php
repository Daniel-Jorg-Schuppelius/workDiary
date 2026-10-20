{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('title', __('Meine Leihgeräte'))

@section('content')
<div class="space-y-4">
    <x-page-toolbar :title="__('Meine Leihgeräte')" :subtitle="__('customer_portal.nav.rentals')">
        @if (app(\App\Services\CustomerPortal\PortalVisibility::class)->allows(auth('customer')->user()?->customer, \App\Enums\CustomerPortal\PortalCapability::RentalRequests))
            <x-slot:actions>
                <x-icon-btn icon="event_available" tone="primary" :href="route('customer.rentals.requests.index')" show-label>{{ __('Verleih-Anfrage stellen') }}</x-icon-btn>
            </x-slot:actions>
        @endif
    </x-page-toolbar>

    <x-card>
    <x-table bare>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('Geräte') }}</x-table.th>
                <x-table.th>{{ __('Zeitraum') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <tr class="hover">
                <td><a class="link font-mono" href="{{ route('customer.rentals.show', $case) }}">{{ $case->number }}</a></td>
                <td>{{ $case->caseAssets->map(fn($ca) => $ca->asset?->name)->filter()->implode(', ') ?: '—' }}</td>
                <td>{{ $case->starts_at->fdate() }} – {{ $case->ends_at->fdate() }}</td>
                <td><x-status-badge tone="plain" size="md" outline>{{ $case->status->label() }}</x-status-badge></td>
            </tr>
        @empty
            <x-table.empty :colspan="4" icon="handyman" :title="__('Keine Verleihvorgänge vorhanden.')" compact />
        @endforelse
    </x-table>
    </x-card>
    <x-pagination :paginator="$cases" standing />
</div>
@endsection

{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : trips.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Fahrtvorschläge'))
@section('nav-title', __('Fahrtvorschläge'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Aus Ihren Standortdaten abgeleitete Fahrten prüfen und ins Fahrtenbuch übernehmen.')">
    @include('location._review_tabs')

    @if (! $canBook)
        <div role="status" class="alert alert-info text-sm">{{ __('Das Fahrtenbuch ist in dieser Organisation nicht aktiv; Fahrtvorschläge lassen sich nur verwerfen.') }}</div>
    @endif

    @if ($trips->isEmpty())
        <x-empty-state framed icon="route" :title="__('Keine offenen Fahrtvorschläge.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Von') }}</th>
                    <th>{{ __('Nach') }}</th>
                    <th>{{ __('Von–Bis') }}</th>
                    <th class="text-end">{{ __('Strecke') }}</th>
                    <th>{{ __('Quelle') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($trips as $trip)
                <tr>
                    <td>{{ $trip->started_at->orgTz()->translatedFormat('d.m.Y') }}</td>
                    <td>{{ $trip->from_label ?? \CommonToolkit\Helper\Geo\GeoHelper::formatLatLon((float) $trip->from_lat, (float) $trip->from_lng, 4) }}</td>
                    <td>{{ $trip->to_label ?? \CommonToolkit\Helper\Geo\GeoHelper::formatLatLon((float) $trip->to_lat, (float) $trip->to_lng, 4) }}</td>
                    <td>{{ $trip->started_at->orgTz()->format('H:i') }}–{{ $trip->ended_at->orgTz()->format('H:i') }}</td>
                    <td class="text-end">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $trip->distance_km, 2, withThousandsSeparator: true) }} km</td>
                    <td>
                        @if ($trip->distance_source === \App\Models\Location\LocationPendingTrip::SOURCE_MATCH)
                            <x-status-badge tone="success" size="xs">{{ __('Kartenabgleich') }}</x-status-badge>
                        @else
                            <x-status-badge tone="warning" size="xs" :title="__('Strecke aus der GPS-Spur ohne Kartenabgleich.')">{{ __('Spur') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($canBook)
                            <x-icon-btn icon="directions_car" tone="success" size="sm" data-entry-modal-trigger :href="route('location.review.trips.confirm.form', $trip)" :label="__('Ins Fahrtenbuch übernehmen')" />
                        @endif
                        <x-action-form :action="route('location.review.trips.dismiss', $trip)"
                                       :confirm="__('Vorschlag verwerfen?')" :confirm-label="__('Verwerfen')">
                            <x-icon-btn icon="close" tone="ghost" size="sm" type="submit" :label="__('Verwerfen')" />
                        </x-action-form>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$trips" standing />
    @endif
</x-index-page>
@endsection

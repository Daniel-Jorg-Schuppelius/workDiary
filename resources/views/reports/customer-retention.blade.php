{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-retention.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Kundenbindung'))
@section('nav-title', __('Kundenbindung'))

@section('content')
@php
    $linkParams = array_filter(array_merge(
        ['lost_days' => $lostDays !== 365 ? $lostDays : null],
        $standardFilters->toQueryParams(),
    ));
    $customerLink = fn (int $id): string => route('reports.customer-project', array_merge($standardFilters->toQueryParams(), [
        'customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $id),
    ]));
    $pct = fn (?float $v): string => $v === null ? '–' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 1) . ' %';
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Kohorten-Retention nach Erstleistungsjahr und Kundenbestandsbrücke.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.customer-retention', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.customer-retention', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.customer-retention" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.customer-retention')" :reset="route('reports.customer-retention')">
        @include('reports._standard_filters', ['idPrefix' => 'customer-retention'])
        <x-filter-field :label="__('Verloren nach (Tage ohne Leistung)')" for="cr-lost-days">
            <input id="cr-lost-days" type="number" name="lost_days" value="{{ $lostDays }}" min="30" class="input input-sm input-bordered w-36" />
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi-tile :label="__('Wiederkehrquote')" :value="$pct($kpis['returningRate'])"
                    :hint="__('aktive Kunden des Vorjahres, die auch im Berichtsjahr aktiv sind')" />
        <x-kpi-tile :label="__('Ø Kundenalter (Jahre)')" :value="$kpis['avgCustomerAgeYears'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kpis['avgCustomerAgeYears'], 1) : '–'" />
        <x-kpi-tile :label="__('Aktive Kunden (Ende)')" :value="$kpis['endActive']" />
        <x-kpi-tile :label="__('Neukunden')" :value="$kpis['newCount']" :tone="$kpis['newCount'] > 0 ? 'success' : 'neutral'" />
        <x-kpi-tile :label="__('Verlorene Kunden')" :value="$kpis['lostCount']" :tone="$kpis['lostCount'] > 0 ? 'warning' : 'success'"
                    :hint="__('seit :days Tagen ohne Leistung', ['days' => $lostDays])" />
    </div>

    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.heatmap :title="__('Kohorten-Retention (Anteil aktiver Kunden je Jahr)')" unit="%"
                          :rows="$cohortHeatmap['rows']" :col-labels="$cohortHeatmap['colLabels']"
                          :max="$cohortHeatmap['max']" :totals="false" :x-label="__('Erstleistungsjahr')"
                          :format="fn (float $v): string => round($v) . ' %'" />
        <x-charts.waterfall :title="__('Kundenbestandsbrücke')" :unit="__('Kunden')" :series="$bridgeSeries"
                            :start-value="$bridge['start']" :start-label="__('Bestand Start')" :end-label="__('Bestand Ende')"
                            :x-label="__('Schritt')" :y-label="__('Kunden')" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('Neukunden') }} ({{ count($bridge['new']) + count($bridge['newChurned']) }})</h2>
            @if ($bridge['new'] === [] && $bridge['newChurned'] === [])
                <p class="text-sm text-base-content/60">{{ __('Keine Neukunden im Zeitraum.') }}</p>
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($bridge['new'] as $c)
                        <li><a href="{{ $customerLink($c['customerId']) }}" class="link link-hover">{{ $c['customerName'] }}</a></li>
                    @endforeach
                    @foreach ($bridge['newChurned'] as $c)
                        <li>
                            <a href="{{ $customerLink($c['customerId']) }}" class="link link-hover">{{ $c['customerName'] }}</a>
                            <span class="badge badge-ghost badge-xs ml-1">{{ __('wieder inaktiv') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('Zurückgewonnen') }} ({{ count($bridge['reactivated']) }})</h2>
            @if ($bridge['reactivated'] === [])
                <p class="text-sm text-base-content/60">{{ __('Keine zurückgewonnenen Kunden im Zeitraum.') }}</p>
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($bridge['reactivated'] as $c)
                        <li><a href="{{ $customerLink($c['customerId']) }}" class="link link-hover">{{ $c['customerName'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('Verlorene Kunden') }} ({{ count($bridge['lost']) }})</h2>
            @if ($bridge['lost'] === [])
                <p class="text-sm text-base-content/60">{{ __('Keine verlorenen Kunden im Zeitraum — gut so.') }}</p>
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($bridge['lost'] as $c)
                        <li><a href="{{ $customerLink($c['customerId']) }}" class="link link-hover">{{ $c['customerName'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <p class="mt-3 text-xs text-base-content/60">
        {{ __('Zeitraum') }}: {{ $label }} · {{ __('Kohortenbasis: Erstleistungsjahr (org-weit, unabhängig vom Zeitraumfilter).') }}
    </p>
</x-page-shell>
@endsection

{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-value.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Kundenwert'))
@section('nav-title', __('Kundenwert'))

@section('content')
@php
    $eur = fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    $linkParams = array_filter(array_merge(
        ['risk_days' => $riskDays !== 60 ? $riskDays : null],
        $standardFilters->toQueryParams(),
    ));
    $hhi = $concentration['hhi'];
    $hhiTone = $hhi === null ? 'neutral'
        : ($hhi > \App\Services\Reporting\CustomerValueReportBuilder::HHI_HIGH ? 'error'
        : ($hhi >= \App\Services\Reporting\CustomerValueReportBuilder::HHI_MODERATE ? 'warning' : 'success'));
    $segmentBadge = [
        'champion' => 'badge-success',
        'loyal' => 'badge-info',
        'potential' => 'badge-ghost',
        'new' => 'badge-primary',
        'at_risk' => 'badge-warning',
        'inactive' => 'badge-ghost',
    ];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('RFM-Segmente, Umsatzkonzentration und gefährdete A-Kunden.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.customer-value', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.customer-value', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.customer-value" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.customer-value')" :reset="route('reports.customer-value')">
        @include('reports._standard_filters', ['idPrefix' => 'customer-value'])
        <x-filter-field :label="__('Risiko-Schwelle (Tage ohne Leistung)')" for="cv-risk-days">
            <input id="cv-risk-days" type="number" name="risk_days" value="{{ $riskDays }}" min="1" class="input input-sm input-bordered w-36" />
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi-tile :label="__('Erlös gesamt')" :value="$eur($concentration['totalRevenue'])" />
        <x-kpi-tile :label="__('Top-5-Anteil')" :value="$concentration['top5Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top5Share'], 1) . ' %' : '–'"
                    :tone="($concentration['top5Share'] ?? 0) > 60 ? 'warning' : 'neutral'"
                    :hint="__('Klumpenrisiko ab ~60 %')" />
        <x-kpi-tile :label="__('Top-10-Anteil')" :value="$concentration['top10Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top10Share'], 1) . ' %' : '–'" />
        <x-kpi-tile :label="__('HHI (Konzentration)')" :value="$hhi ?? '–'" :tone="$hhiTone"
                    :hint="__('unter 1500 unkritisch, über 2500 hoch')" />
        <x-kpi-tile :label="__('Gefährdete A-Kunden')" :value="count($riskRows)"
                    :tone="count($riskRows) > 0 ? 'warning' : 'success'"
                    :hint="__('hoher Erlös, aber seit :days Tagen ohne Leistung', ['days' => $riskDays])" />
    </div>

    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.pareto :title="__('Erlös je Kunde (Top 20)')" unit="€" :series="$revenueSeries" :x-label="__('Kunde')" y-label="€" />
        <x-charts.scatter :title="__('Erlös nach Inaktivität (rechts = länger her)')" unit="€"
                          :series="$riskScatter['series']" :percentiles="$riskScatter['percentiles']"
                          :x-label="__('Kunde (Tage seit letzter Leistung)')" y-label="€" />
    </div>
    <x-charts.bar-h :title="__('Kunden je Segment')" :unit="__('Kunden')" :series="$segmentSeries" :x-label="__('Segment')" :y-label="__('Kunden')" />

    <x-card class="mt-4">
        <h2 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('Gefährdete A-Kunden') }}</h2>
        @if (count($riskRows) === 0)
            <p class="text-sm text-base-content/60">{{ __('Kein A-Kunde ist seit :days Tagen ohne Leistung — gut so.', ['days' => $riskDays]) }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kunde') }}</th>
                        <th class="text-right">{{ __('Erlös im Zeitraum') }}</th>
                        <th class="text-right">{{ __('Tage seit letzter Leistung') }}</th>
                        <th>{{ __('Erlösverlauf (12 Monate)') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($riskRows as $row)
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('reports.customer-project', array_merge($standardFilters->toQueryParams(), ['customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $row['customerId'])])) }}" class="link link-hover">
                                {{ $row['customerName'] }}
                            </a>
                        </td>
                        <td class="text-right tabular-nums">{{ $eur($row['revenue']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['recencyDays'] }}</td>
                        <td><x-charts.sparkline :values="$riskSparklines[$row['customerId']] ?? []" unit="€" :label="__('Monatserlös')" /></td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card class="mt-4">
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if ($rows->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">analytics</span>' :title="__('Keine Kundendaten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Segment') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Tage seit Leistung') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Aktivitätstage') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erlös') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Fakturiert') }}</x-table.th>
                        <x-table.th sort type="number" align="right">R</x-table.th>
                        <x-table.th sort type="number" align="right">F</x-table.th>
                        <x-table.th sort type="number" align="right">M</x-table.th>
                        <x-table.th sort type="string" align="right">{{ __('Erste Leistung') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('reports.customer-project', array_merge($standardFilters->toQueryParams(), ['customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $row['customerId'])])) }}" class="link link-hover">
                                {{ $row['customerName'] }}
                            </a>
                        </td>
                        <td><span class="badge badge-sm {{ $segmentBadge[$row['segment']] ?? 'badge-ghost' }}">{{ $segmentLabels[$row['segment']] ?? $row['segment'] }}</span></td>
                        <td class="text-right tabular-nums">{{ $row['recencyDays'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['frequencyDays'] }}</td>
                        <td class="text-right tabular-nums">{{ $eur($row['revenue']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['invoiced'] > 0 ? $eur($row['invoiced']) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['r'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['f'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['m'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['firstActivity'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

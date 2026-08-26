{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : utilization.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Auslastung & Realisierung'))
@section('nav-title', __('Auslastung'))

@section('content')
@php
    $linkParams = array_filter($standardFilters->toQueryParams());
    $pct = fn (?float $v): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 1) . ' %';
    $hours = fn (int $min): string => \App\Support\Formats::duration($min, 'clock');
    $toneMap = ['success' => 'success', 'warning' => 'warning', 'error' => 'error', 'neutral' => 'neutral'];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Auslastung, abrechenbare Quote und Realisierung — je Person und im Trend.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.utilization', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.utilization', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.utilization', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.utilization" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.utilization')" :reset="route('reports.utilization')">
        @include('reports._standard_filters', ['idPrefix' => 'utilization'])
    </x-filter-bar>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi-tile :label="__('Auslastung gesamt')" :value="$pct($totals['utilization'])"
                    :tone="$toneMap[$orgEval['tone'] ?? 'neutral'] ?? 'neutral'"
                    :hint="$orgEval !== null ? __('Ziel: :target %', ['target' => $orgEval['target']]) : __('erfasste Zeit / Soll-Zeit')" />
        <x-kpi-tile :label="__('Abrechenbare Quote')" :value="$pct($totals['billableRate'])"
                    :tone="$toneMap[$billableEval['tone'] ?? 'neutral'] ?? 'neutral'"
                    :hint="$billableEval !== null ? __('Ziel: :target %', ['target' => $billableEval['target']]) : __('abrechenbare / erfasste Zeit')" />
        <x-kpi-tile :label="__('Realisierung')" :value="$hasInvoiceData ? $pct($totals['realization']) : '—'"
                    :hint="$hasInvoiceData ? __('fakturierte / abrechenbare Zeit') : __('ohne lokale Fakturierung keine Datenbasis')" />
        <x-kpi-tile :label="__('Soll-Zeit')" :value="$hours($totals['targetMinutes'])" />
        <x-kpi-tile :label="__('Erfasste Zeit')" :value="$hours($totals['trackedMinutes'])" />
    </div>

    <x-charts.bullet :title="__('Auslastung je Person gegen Zielwert')" unit="%" :series="$bulletSeries"
                     :x-label="__('Person')" :y-label="__('Auslastung')" :target-label="__('Ziel')"
                     :note="__('Auslastung = erfasste Zeit ÷ Soll-Zeit aus dem Arbeitszeitmodell; Ziel aus Admin → Zielwerte; Klick öffnet den Monatsbericht der Person.')" />

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.line :title="__('Auslastung im Monatsverlauf')" unit="%" :series="$trendSeries"
                       :x-label="__('Monat')" :y-label="__('Auslastung %')"
                       :note="__('Klick auf einen Monat schränkt diesen Bericht auf den Monat ein.')" />
        <x-charts.boxplot :title="__('Verteilung der Auslastung je Monat')" unit="%" :series="$boxSeries"
                          :x-label="__('Monat')" :y-label="__('Auslastung %')"
                          :note="__('Eine Box je Monat über die Auslastung aller Personen mit Soll-Zeit; Ausreißer nach unten sind meist Planungs- oder Erfassungslücken.')" />
    </div>

    <x-card class="mt-4">
        <div class="mb-3 text-xs text-muted">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if ($rows === [])
            <x-empty-state icon="analytics" :title="__('Keine Soll- oder Ist-Zeiten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Person') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Soll') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erfasst') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Abrechenbar') }}</x-table.th>
                        @if ($hasInvoiceData)<x-table.th sort type="number" align="right">{{ __('Fakturiert') }}</x-table.th>@endif
                        <x-table.th sort type="number" align="right">{{ __('Auslastung') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Abrechenbare Quote') }}</x-table.th>
                        @if ($hasInvoiceData)<x-table.th sort type="number" align="right">{{ __('Realisierung') }}</x-table.th>@endif
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('reports.month-by-user-team', ['user' => \App\Support\Sqid::encode(\App\Models\User::class, $row['userId'])]) }}" class="link link-hover">{{ $row['userName'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $hours($row['targetMinutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $hours($row['trackedMinutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $hours($row['billableMinutes']) }}</td>
                        @if ($hasInvoiceData)<td class="text-right tabular-nums">{{ $hours($row['invoicedMinutes']) }}</td>@endif
                        <td class="text-right tabular-nums">{{ $pct($row['utilization']) }}</td>
                        <td class="text-right tabular-nums">{{ $pct($row['billableRate']) }}</td>
                        @if ($hasInvoiceData)<td class="text-right tabular-nums">{{ $pct($row['realization']) }}</td>@endif
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

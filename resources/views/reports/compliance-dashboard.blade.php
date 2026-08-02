{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : compliance-dashboard.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Arbeitszeit-Compliance-Dashboard (Feature 006, Rang 39): KPI-Kacheln,
  Verstoß-Zeitreihe je Regel und Team-Aggregation — bewusst teambezogen
  (kein Personen-Scoring in der Übersicht); Drilldown in den Einzelreport.
--}}

@extends('layouts.app')
@section('title', __('Compliance-Dashboard'))
@section('nav-title', __('Compliance-Dashboard'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ $from }} – {{ $to }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="table_view" tone="outline" size="sm" :href="route('reports.arbzg-compliance')" show-label>{{ __('Einzelreport') }}</x-icon-btn>
                <x-icon-btn icon="fact_check" tone="outline" size="sm" :href="route('reports.compliance.history')" show-label>{{ __('compliance.history.nav') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.compliance.dashboard')" :reset="route('reports.compliance.dashboard')">
        @include('reports._standard_filters', ['idPrefix' => 'comp-dash'])
    </x-filter-bar>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Befunde gesamt')" :value="$summary['total']" tone="primary" format="int"
                    :href="route('reports.arbzg-compliance')" />
        <x-kpi-tile :label="__('Betroffene Mitarbeitende')" :value="$summary['employees']" tone="info" format="int" />
        <x-kpi-tile :label="__('Offen (ohne Korrektur)')" :value="$openCount" :tone="$openCount > 0 ? 'error' : 'success'" format="int" />
        <x-kpi-tile :label="__('Mit genehmigter Korrektur')" :value="$correctedCount" tone="success" format="int" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($kinds as $kind)
            <x-kpi-tile :label="$thresholds[$kind] ?? $kind"
                        :value="$summary['by_kind'][$kind] ?? 0"
                        :tone="($summary['by_kind'][$kind] ?? 0) > 0 ? 'warning' : 'neutral'"
                        format="int"
                        :href="route('reports.arbzg-compliance', ['kind' => $kind])" />
        @endforeach
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.line :title="__('Offene Befunde je Monat')" :unit="__('Befunde')"
                       :series="$openMonthlySeries" :x-label="__('Monat')" :y-label="__('Offen')" />
        <x-charts.stacked-bar :title="__('Befunde je Monat nach Verstoßart')" :unit="__('Befunde')"
                              :series="$monthlyKindSeries" :bands="$kindBands" :x-label="__('Monat')" />
    </div>

    <x-card :title="__('Verstöße je Regel und Monat')" icon="calendar_month">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Monat') }}</th>
                    @foreach ($kinds as $kind)
                        <th class="text-right">{{ $thresholds[$kind] ?? $kind }}</th>
                    @endforeach
                    <th class="text-right">{{ __('Summe') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($months as $month => $byKind)
                <tr>
                    <td class="tabular-nums">{{ $month }}</td>
                    @foreach ($kinds as $kind)
                        <td class="text-right tabular-nums">{{ $byKind[$kind] ?? 0 }}</td>
                    @endforeach
                    <td class="text-right font-semibold tabular-nums">{{ array_sum($byKind) }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="count($kinds) + 2" icon="rule" :title="__('Keine Daten im Zeitraum.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('Befunde je Team')" icon="groups">
        @if ($byTeam === [])
            <x-empty-state icon="verified" :title="__('Keine Befunde im Zeitraum.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Team') }}</th>
                        <th class="text-right">{{ __('Befunde') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($byTeam as $teamName => $count)
                    <tr>
                        <td>{{ $teamName }}</td>
                        <td class="text-right tabular-nums">{{ $count }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

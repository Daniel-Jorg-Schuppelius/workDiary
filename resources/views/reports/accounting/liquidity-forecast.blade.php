{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : liquidity-forecast.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  13-Wochen-Liquiditätsvorschau (Feature 136, MVP-701): Startsaldo, Ein-/
  Auszahlungen je ISO-Woche mit Quellen-Aufschlüsselung und kumuliertem
  Saldo. Eine Erwartung, kein Kontostand — der Vorbehalt steht oben.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.liquidity_forecast.title'))
@section('nav-title', __('accounting.reports.card.liquidity_forecast.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.reports.forecast.subtitle', ['date' => $as_of->fdate(), 'weeks' => $weeks])">
        <x-slot:actions>
            @foreach ($horizons as $horizon)
                <x-icon-btn icon="date_range" size="sm" :tone="$horizon === $weeks ? 'primary' : 'ghost'" show-label
                            :href="route('reports.accounting.liquidity-forecast', ['weeks' => $horizon])"
                            :label="__('accounting.reports.forecast.horizon', ['weeks' => $horizon])" />
            @endforeach
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.liquidity-forecast', ['weeks' => $weeks, 'export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.liquidity-forecast', ['weeks' => $weeks, 'export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.liquidity-forecast', ['weeks' => $weeks, 'export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ __('accounting.reports.forecast.hint') }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('accounting.reports.forecast.kpi.opening')" :value="$opening_balance" />
            <x-kpi-tile :label="__('accounting.reports.forecast.kpi.inflow')" :value="$totals['inflow']" tone="success" />
            <x-kpi-tile :label="__('accounting.reports.forecast.kpi.outflow')" :value="$totals['outflow']" tone="warning" />
            <x-kpi-tile :label="__('accounting.reports.forecast.kpi.min_closing')" :value="$totals['min_closing']"
                        :tone="(float) $totals['min_closing'] < 0 ? 'error' : 'neutral'"
                        :hint="$totals['min_week'] !== '' ? __('accounting.reports.forecast.kpi.min_week', ['week' => $totals['min_week']]) : null" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-charts.line :title="__('accounting.reports.forecast.chart.closing')" unit="€" :series="$closingSeries"
                           :x-label="__('accounting.reports.forecast.column.week')" :y-label="__('accounting.reports.forecast.column.closing')"
                           :computed-at="$as_of" />
            <x-charts.bar :title="__('accounting.reports.forecast.chart.flows')" unit="€" :series="$flowSeries"
                          :x-label="__('accounting.reports.forecast.column.week')" :y-label="__('accounting.reports.forecast.column.inflow')"
                          :y2-label="__('accounting.reports.forecast.column.outflow')" :computed-at="$as_of" />
        </div>

        @php($columns = 2 + count($sources) + 4)
        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.reports.forecast.column.week') }}</th>
                    <th>{{ __('accounting.reports.forecast.column.period') }}</th>
                    @foreach ($sources as $source)
                        <th class="text-right">{{ __('accounting.reports.forecast.source.' . $source) }}</th>
                    @endforeach
                    <th class="text-right">{{ __('accounting.reports.forecast.column.inflow') }}</th>
                    <th class="text-right">{{ __('accounting.reports.forecast.column.outflow') }}</th>
                    <th class="text-right">{{ __('accounting.reports.forecast.column.net') }}</th>
                    <th class="text-right">{{ __('accounting.reports.forecast.column.closing') }}</th>
                </tr>
            </x-slot:head>
            <tr class="font-semibold">
                <td colspan="{{ $columns - 1 }}">{{ __('accounting.reports.forecast.kpi.opening') }}</td>
                <td class="text-right font-mono">{{ $opening_balance }}</td>
            </tr>
            @foreach ($buckets as $bucket)
                <tr class="hover font-medium">
                    <td class="whitespace-nowrap">{{ $bucket['label'] }}</td>
                    <td class="whitespace-nowrap">{{ $bucket['from']->fdate() }} – {{ $bucket['to']->fdate() }}</td>
                    @foreach ($sources as $source)
                        @php($sourceNet = \CommonToolkit\Helper\Data\NumberHelper::subtractPrecise($bucket['sources'][$source]['in'], $bucket['sources'][$source]['out'], 2))
                        <td class="text-right font-mono {{ (float) $sourceNet === 0.0 ? 'text-muted' : '' }}">{{ (float) $sourceNet === 0.0 ? '—' : $sourceNet }}</td>
                    @endforeach
                    <td class="text-right font-mono">{{ $bucket['inflow'] }}</td>
                    <td class="text-right font-mono">{{ $bucket['outflow'] }}</td>
                    <td class="text-right font-mono">{{ $bucket['net'] }}</td>
                    <td class="text-right font-mono {{ (float) $bucket['closing'] < 0 ? 'text-error' : '' }}">{{ $bucket['closing'] }}</td>
                </tr>
                @foreach ($bucket['items'] as $item)
                    <tr class="text-xs text-base-content/70">
                        <td></td>
                        <td class="whitespace-nowrap">{{ $item['expected_on']->fdate() }}</td>
                        <td colspan="{{ count($sources) }}">
                            {{ $item['label'] !== '' ? $item['label'] : '—' }}
                            <span class="badge badge-ghost badge-sm ml-1">{{ __('accounting.reports.forecast.source.' . $item['source']) }}</span>
                            @if ($item['note'])
                                <span class="ml-1">· {{ $item['note'] }}</span>
                            @endif
                        </td>
                        <td class="text-right font-mono">{{ $item['direction'] === 'in' ? $item['amount'] : '' }}</td>
                        <td class="text-right font-mono">{{ $item['direction'] === 'out' ? $item['amount'] : '' }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
        </x-table>
    </x-index-page>
@endsection

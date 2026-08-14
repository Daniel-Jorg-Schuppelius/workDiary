{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : month-by-user-team.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Team-Monatsreport') . ' — ' . $year)
@section('nav-title', __('Team-Monatsreport') . ' — ' . $year)

@section('content')
@php
    $fmt = fn (int $min): string => $min <= 0 ? '–' : \App\Support\Formats::duration($min, 'clock', withUnit: false);
    $money = function (float $val): string {
        return \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($val, 2, withThousandsSeparator: true) . ' €';
    };
    $fmtChart = fn (int|float $min): string => \App\Support\Formats::duration((int) $min, 'clock', withUnit: false);
    $linkParams = $standardFilters->toQueryParams();
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Stunden je Mitarbeiter und Monat über das Jahr.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.month-by-user-team', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_chart" tone="outline" size="sm"
                            :href="route('reports.month-by-user-team', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>XLSX</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.month-by-user-team', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.month-by-user-team')" :reset="route('reports.month-by-user-team')">
        @include('reports._standard_filters', ['idPrefix' => 'month-by-user-team'])
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('Stunden je Mitarbeiter')" unit="h" :series="$userHoursSeries" :median="$hoursMedian" :x-label="__('Mitarbeiter')" :y-label="__('Stunden')" />
        <x-charts.heatmap
            :title="__('Stunden je Mitarbeiter und Monat')"
            unit="h"
            :rows="$heatmapRows"
            :col-labels="array_values($monthLabels)"
            :x-label="__('Mitarbeiter')"
            :format="$fmtChart"
        />
    </div>

    <x-card>
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $year }}</h2>
            <div class="flex items-baseline gap-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ Std.</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $yearTotal > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $fmt($yearTotal) }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ €</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $yearRate > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $money($yearRate) }}
                    </span>
                </div>
            </div>
        </div>

        @if (count($byUser) === 0)
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">view_module</span>' :title="__('Keine Einträge in diesem Jahr.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        @foreach ($monthLabels as $label)
                            <x-table.th sort type="duration" align="right">{{ $label }}</x-table.th>
                        @endforeach
                        <x-table.th sort type="duration" align="right">Σ {{ __('Stunden') }}</x-table.th>
                        <x-table.th sort type="number" align="right">Σ €</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>Σ {{ __('Monat') }}</td>
                        @foreach ($monthTotals as $m)
                            <td class="text-right">{{ $fmt($m) }}</td>
                        @endforeach
                        <td class="text-right">{{ $fmt($yearTotal) }}</td>
                        <td class="text-right">{{ $money($yearRate) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($byUser as $uid => $row)
                    <tr>
                        <td class="font-medium">{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                        @foreach ($row['months'] as $minutes)
                            <td class="text-right text-sm @if ($minutes === 0) opacity-30 @endif" data-sort-value="{{ (int) $minutes }}">{{ $fmt($minutes) }}</td>
                        @endforeach
                        <td class="text-right font-semibold" data-sort-value="{{ (int) $row['total'] }}">{{ $fmt($row['total']) }}</td>
                        <td class="text-right" data-sort-value="{{ (float) $row['rate'] }}">{{ $money($row['rate']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

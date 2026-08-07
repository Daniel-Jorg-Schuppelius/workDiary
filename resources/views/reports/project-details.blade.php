@extends('layouts.app')
@section('title', __('Projekt-Details'))
@section('nav-title', __('Projekt-Details'))

@section('content')
@php
    $fmt = fn (int $min): string => $min <= 0 ? '–' : \App\Support\Formats::duration($min, 'clock');
    $money = function (float $val): string {
        return \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($val, 2, withThousandsSeparator: true) . ' €';
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Stunden und Erlöse je Monat für ein einzelnes Projekt.')">
            <x-slot:actions>
                @if ($project)
                    <x-icon-btn icon="download" tone="outline" size="sm"
                                :href="route('reports.project-details', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))"
                                show-label>CSV</x-icon-btn>
                    <x-icon-btn icon="table_chart" tone="outline" size="sm"
                                :href="route('reports.project-details', array_merge($standardFilters->toQueryParams(), ['export' => 'xlsx']))"
                                show-label>XLSX</x-icon-btn>
                    <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                                :href="route('reports.project-details', array_merge($standardFilters->toQueryParams(), ['export' => 'pdf']))"
                                show-label>PDF</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.project-details')" :reset="route('reports.project-details')">
        @include('reports._standard_filters', ['idPrefix' => 'project-details', 'projectRequired' => true])
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.line :title="__('Stundenverlauf im Zeitraum')" unit="h" :series="$timelineSeries" :x-label="__('Zeitpunkt')" :y-label="__('Stunden')" />
        <x-charts.bar :title="__('Ist- und Plan-Stunden je Monat')" unit="h" :series="$planIstSeries" :median="$planIstMedian" :x-label="__('Monat')" :y-label="__('Ist')" :y2-label="__('Plan')" />
    </div>
    <x-charts.stacked-bar :title="__('Stunden nach Auftragstyp je Monat')" unit="h" :series="$typeMonthlySeries" :bands="$typeBands" :x-label="__('Monat')" />

    @if (! $project)
        <div class="alert">{{ __('Kein Projekt vorhanden.') }}</div>
    @else
        <x-card>
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold">
                    {{ $project->name }}
                    @if ($project->customer)
                        <span class="text-sm text-base-content/60">– {{ $project->customer->name }}</span>
                    @endif
                </h2>
                <div class="flex items-baseline gap-4">
                    <div class="flex items-baseline gap-2">
                        <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ Std.</span>
                        <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $yearMinutes > 0 ? 'text-primary' : 'text-base-content/50' }}">{{ $fmt($yearMinutes) }}</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ €</span>
                        <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $yearRate > 0 ? 'text-primary' : 'text-base-content/50' }}">{{ $money($yearRate) }}</span>
                    </div>
                </div>
            </div>

            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Monat') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Stunden') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erlös') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>Σ {{ __('Jahr') }}</td>
                        <td class="text-right">{{ $fmt($yearMinutes) }}</td>
                        <td class="text-right">{{ $money($yearRate) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($monthMatrix as $idx => $row)
                    <tr>
                        <td>{{ $monthLabels[$idx] ?? $idx }}</td>
                        <td class="text-right @if ($row['minutes'] === 0) opacity-30 @endif" data-sort-value="{{ (int) $row['minutes'] }}">{{ $fmt($row['minutes']) }}</td>
                        <td class="text-right" data-sort-value="{{ (float) $row['rate'] }}">{{ $money($row['rate']) }}</td>
                    </tr>
                @endforeach
            </x-table>

            @if (count($byUser) > 0)
                <h3 class="mt-6 mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Aufteilung pro Mitarbeiter') }}</h3>
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort type="duration" align="right">{{ __('Stunden') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Erlös') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    <x-slot:foot>
                        <tr class="font-bold">
                            <td>Σ {{ __('Gesamt') }}</td>
                            <td class="text-right">{{ $fmt($yearMinutes) }}</td>
                            <td class="text-right">{{ $money($yearRate) }}</td>
                        </tr>
                    </x-slot:foot>
                    @foreach ($byUser as $uid => $row)
                        <tr>
                            <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                            <td class="text-right" data-sort-value="{{ (int) $row['minutes'] }}">{{ $fmt($row['minutes']) }}</td>
                            <td class="text-right" data-sort-value="{{ (float) $row['rate'] }}">{{ $money($row['rate']) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection

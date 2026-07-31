@extends('layouts.app')
@section('title', __('Coverage / Soll-Ist-Besetzung'))
@section('nav-title', __('Coverage'))

@section('content')
@php
    $pct = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %';
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Soll-Ist-Besetzung je Schichttyp inkl. Erfüllung und Unterdeckungstagen.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.coverage', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.coverage', array_merge($standardFilters->toQueryParams(), ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.coverage')" :reset="route('reports.coverage')">
        @include('reports._standard_filters', ['idPrefix' => 'coverage'])
    </x-filter-bar>

    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.heatmap
            :title="__('Deckungsgrad je Schichttyp und Wochentag')"
            unit="%"
            :rows="$coverageHeatmapRows"
            :col-labels="$weekdayLabels"
            :x-label="__('Schichttyp')"
            :totals="false"
        />
        <x-charts.bar :title="__('Fehlende Personentage je Woche')" :unit="__('Personentage')"
                      :series="$underfilledWeekSeries" :x-label="__('Woche')" :y-label="__('Fehlend')" />
    </div>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Schichttypen')" :value="$totals['shift_types']" :hint="$daySpan . ' ' . __('Tage')" />
        <x-kpi-tile :label="__('Soll (Personentage)')" :value="$totals['required']" />
        <x-kpi-tile :label="__('Ist (Personentage)')" :value="$totals['scheduled']"
                    :hint="($totals['gap'] > 0 ? '+' : '') . $totals['gap']" />
        <x-kpi-tile :label="__('Erfüllung')" :value="$totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–'" />
        <x-kpi-tile :label="__('Tage mit Unterdeckung')" :value="$totals['days_under']"
                    :tone="$totals['days_under'] > 0 ? 'error' : 'neutral'" />
    </div>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Pro Schichttyp') }}</h3>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">shield_person</span>' :title="__('Keine Soll-Vorgaben oder Plan-Einträge im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Schichttyp') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Soll') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ist') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Differenz') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erfüllung') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Tage unter') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $totals['required'] }}</td>
                        <td class="text-right tabular-nums">{{ $totals['scheduled'] }}</td>
                        <td class="text-right tabular-nums {{ $totals['gap'] < 0 ? 'text-error' : '' }}">
                            {{ $totals['gap'] > 0 ? '+' : '' }}{{ $totals['gap'] }}
                        </td>
                        <td class="text-right tabular-nums">{{ $totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–' }}</td>
                        <td class="text-right tabular-nums">{{ $totals['days_under'] }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-semibold">
                            @if ($r['shiftType']->color)
                                <span class="mr-2 inline-block size-2 rounded-full align-middle" style="background-color: {{ $r['shiftType']->color }};"></span>
                            @endif
                            {{ $r['shiftType']->name }}
                            @if ($r['shiftType']->abbreviation)
                                <span class="ml-1 text-xs text-base-content/50">{{ $r['shiftType']->abbreviation }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $r['required'] }}</td>
                        <td class="text-right tabular-nums">{{ $r['scheduled'] }}</td>
                        <td class="text-right tabular-nums {{ $r['gap'] < 0 ? 'text-error font-semibold' : ($r['gap'] > 0 ? 'text-success' : '') }}" data-sort-value="{{ (int) $r['gap'] }}">
                            {{ $r['gap'] > 0 ? '+' : '' }}{{ $r['gap'] }}
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $r['fill_rate'] ?? -1 }}">{{ $r['fill_rate'] !== null ? $pct($r['fill_rate']) : '–' }}</td>
                        <td class="text-right tabular-nums {{ $r['days_under'] > 0 ? 'text-error' : '' }}">{{ $r['days_under'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    @if (! empty($underfilled))
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-error/80">
                {{ __('Tage mit Unterdeckung') }}
                <span class="text-base-content/50">({{ count($underfilled) }})</span>
            </h3>
            <x-table size="xs" table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="date">{{ __('Datum') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Schichttyp') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Soll') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ist') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Lücke') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($underfilled as $u)
                    <tr>
                        <td class="tabular-nums" data-sort-value="{{ \Carbon\Carbon::parse($u['date'])->format('Y-m-d') }}">{{ \Carbon\Carbon::parse($u['date'])->translatedFormat('D, d.m.Y') }}</td>
                        <td>{{ $u['shiftType']->name }}</td>
                        <td class="text-right tabular-nums">{{ $u['required'] }}</td>
                        <td class="text-right tabular-nums">{{ $u['scheduled'] }}</td>
                        <td class="text-right tabular-nums text-error font-semibold">{{ $u['gap'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection

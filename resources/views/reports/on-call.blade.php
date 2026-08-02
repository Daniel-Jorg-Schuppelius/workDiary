@extends('layouts.app')
@section('title', __('Notdienst-Auswertung'))
@section('nav-title', __('Notdienst-Auswertung'))

@section('content')
@php
    $fmt = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    $pct = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %';
    $fmtChart = fn (int|float $min): string => \App\Support\Formats::duration((int) $min, 'clock', withUnit: false);
    $linkParams = array_filter(array_merge(
        ['scope' => $isAdmin ? $scope : null],
        $standardFilters->toQueryParams(),
    ));
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Bereitschaftsschichten, aktive Einsätze und Aktiv-Anteil je Mitarbeiter.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.on-call', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.on-call', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.on-call')" :reset="route('reports.on-call')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine Bereitschaft') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        @include('reports._standard_filters', ['idPrefix' => 'on-call'])
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.heatmap
            :title="__('Bereitschaft je Mitarbeiter und Woche')"
            unit="h"
            :rows="$heatmapRows"
            :col-labels="$weekLabels"
            :x-label="__('Mitarbeiter')"
            :format="$fmtChart"
        />
        <x-charts.bar :title="__('Einsätze je Monat')" :unit="__('Einsätze')" :series="$monthlyAssignmentSeries" :x-label="__('Monat')" :y-label="__('Anzahl')" />
    </div>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Mitarbeiter')" :value="$totals['users']" />
        <x-kpi-tile :label="__('Bereitschaft')" :value="$fmt($totals['shift_minutes'])" :hint="$totals['shift_count'] . ' ' . __('Schichten')" />
        <x-kpi-tile :label="__('Aktiv-Einsätze')" :value="$fmt($totals['assignment_minutes'])" :hint="$totals['assignment_count'] . ' ' . __('Einsätze')" />
        <x-kpi-tile :label="__('Aktiv-Anteil')" :value="$totals['ratio'] !== null ? $pct($totals['ratio']) : '–'" />
    </div>

    <x-card>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">medical_services</span>' :title="__('Keine Bereitschaftszeiten im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Schichten') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Bereitschaft') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Einsätze') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Einsatzzeit') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Aktiv-Anteil') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $totals['shift_count'] }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($totals['shift_minutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $totals['assignment_count'] }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($totals['assignment_minutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-semibold">{{ $r['user']->name }}</td>
                        <td class="text-right tabular-nums">{{ $r['shift_count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['shift_minutes'] }}">{{ $fmt($r['shift_minutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $r['assignment_count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['assignment_minutes'] }}">{{ $fmt($r['assignment_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $r['ratio'] ?? -1 }}">{{ $r['ratio'] !== null ? $pct($r['ratio']) : '–' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

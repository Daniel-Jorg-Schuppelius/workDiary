{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : operations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Operations'))
@section('nav-title', __('Operations-Auswertung'))

@section('content')
@php
    $pct = fn (?float $v) => $v !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %' : '–';
    $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    $num = fn (float $v, int $d = 2) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, $d, withThousandsSeparator: true);
    $statusLabels = [
        'planned'      => __('Geplant'),
        'assigned'     => __('Zugewiesen'),
        'in_progress'  => __('In Arbeit'),
        'done'         => __('Erledigt'),
        'cancelled'    => __('Storniert'),
        'open'         => __('Offen'),
        'completed'    => __('Abgeschlossen'),
        'draft'        => __('Entwurf'),
    ];
    $prioLabels = [
        'low'    => __('Niedrig'),
        'normal' => __('Normal'),
        'medium' => __('Mittel'),
        'high'   => __('Hoch'),
        'urgent' => __('Dringend'),
    ];
    $label = fn (array $map, string $key) => $map[$key] ?? $key;
    $linkParams = array_filter(array_merge(
        ['scope' => $isAdmin ? $scope : null],
        $standardFilters->toQueryParams(),
    ));
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Service-Aufträge, Tasks und Touren auf einen Blick.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.operations', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.operations', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.operations', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.operations')" :reset="route('reports.operations')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        @include('reports._standard_filters', ['idPrefix' => 'operations', 'statusOptions' => $statusOptions, 'statusLabel' => __('Auftragsstatus')])
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('Service-Aufträge: erstellt vs. erledigt je Woche')" :unit="__('Aufträge')" :series="$weeklyFlowSeries" :x-label="__('Woche')" :y-label="__('Anzahl')" :y2-label="__('Erledigt')" />
        <x-charts.bar-h :title="__('Backlog je Kunde (Top 15)')" :unit="__('Aufträge')" :series="$backlogSeries" :x-label="__('Kunde')" :y-label="__('Offene Aufträge')" />
    </div>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Service-Aufträge')" :value="$orders['total']"
                    :hint="__('Abschluss') . ': ' . $pct($orders['completion_rate'])" />
        <x-kpi-tile :label="__('Servicezeit Σ')" :value="$fmtMin($orders['service_minutes'])" />
        <x-kpi-tile :label="__('Tasks')" :value="$tasks['total']"
                    :tone="$tasks['overdue'] > 0 ? 'warning' : 'neutral'"
                    :hint="$tasks['overdue'] . ' ' . __('überfällig') . ' · ' . $pct($tasks['completion_rate'])" />
        <x-kpi-tile :label="__('Touren')" :value="$tours['total']"
                    :hint="$num($tours['planned_distance_km'], 1) . ' km · ' . $fmtMin($tours['planned_minutes'])" />
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Service-Aufträge – Status') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($orders['by_status'] as $st => $c)
                    <tr><td>{{ $label($statusLabels, $st) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
            <h3 class="mt-4 mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Service-Aufträge – Priorität') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Priorität') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($orders['by_priority'] as $p => $c)
                    <tr><td>{{ $label($prioLabels, $p) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Tasks – Status') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($tasks['by_status'] as $st => $c)
                    <tr><td>{{ $label($statusLabels, $st) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
            <h3 class="mt-4 mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Tasks – Priorität') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Priorität') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($tasks['by_priority'] as $p => $c)
                    <tr><td>{{ $label($prioLabels, $p) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
        </x-card>
    </div>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Touren – pro Mitarbeiter') }}</h3>
        @if (empty($tours['per_user']))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">engineering</span>' :title="__('Keine Touren im Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Touren') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Plan-km') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Plan-Dauer') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $tours['total'] }}</td>
                        <td class="text-right tabular-nums">{{ $num($tours['planned_distance_km'], 1) }} km</td>
                        <td class="text-right tabular-nums">{{ $fmtMin($tours['planned_minutes']) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($tours['per_user'] as $u)
                    <tr>
                        <td class="font-semibold">{{ $u['user']->name }}</td>
                        <td class="text-right tabular-nums">{{ $u['count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $u['distance_km'] }}">{{ $num($u['distance_km'], 1) }} km</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $u['minutes'] }}">{{ $fmtMin($u['minutes']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

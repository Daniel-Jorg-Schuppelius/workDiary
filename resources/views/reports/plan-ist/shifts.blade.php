{{--
  Created on   : Sat Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : shifts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Plan/Ist Schicht-Dimension (A14 · MVP-333, Konzept §2.3): Soll aus den
  sichtbaren geplanten Schichten × Fensterdauer, Ist aus der Überlappung der
  Anwesenheiten mit dem Schichtfenster; Unterdeckung wird hervorgehoben.
--}}

@extends('layouts.app')
@section('title', __('Plan/Ist — Schichten'))
@section('nav-title', __('Plan/Ist — Schichten'))

@section('content')
@php
    $fmtH = fn (int $minutes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes / 60, 1, withThousandsSeparator: true) . ' h';
    $chartSeries = array_map(fn (array $b): array => [
        'x' => $b['key'],
        'y' => round($b['plan_minutes'] / 60, 1),
        'y2' => round($b['actual_minutes'] / 60, 1),
    ], $report['buckets']);
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ __('Plan/Ist — Schichten') }}</x-slot:title>
            <x-slot:subtitle>{{ $from->fdate() }} – {{ $to->fdate() }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.plan-ist._dimensions')

    {{-- Gemeinsame Filterleiste statt freiem GET-Formular (Vollaudit 2026-07, N58). --}}
    <x-filter-bar :action="route('reports.plan-ist.shifts')" :reset="route('reports.plan-ist.shifts')">
        <x-date-range :from="$from->toDateString()" :to="$to->toDateString()" class="w-72 shrink-0" />
        <x-filter-field :label="__('Gruppierung')" for="plan-ist-group">
            <select id="plan-ist-group" name="group" class="select select-sm select-bordered shrink-0">
                <option value="day" @selected($group === 'day')>{{ __('Tag') }}</option>
                <option value="week" @selected($group === 'week')>{{ __('Woche') }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($report['rows'] === [])
        <x-card>
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">event_repeat</span>'
                           :title="__('Keine geplanten Schichten im Zeitraum.')"
                           :message="__('Soll-Werte entstehen aus veröffentlichten oder bestätigten Schichten des Schichtplans.')" />
        </x-card>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile :label="__('Plan')" :value="$fmtH($report['totals']['plan_minutes'])" tone="info" />
            <x-kpi-tile :label="__('Ist')" :value="$fmtH($report['totals']['actual_minutes'])" tone="primary" />
            <x-kpi-tile :label="__('Differenz')" :value="$fmtH($report['totals']['delta_minutes'])" :tone="$report['totals']['delta_minutes'] < 0 ? 'warning' : 'success'" />
            <x-kpi-tile :label="__('Abdeckung')" :value="$report['totals']['coverage_pct'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($report['totals']['coverage_pct'], 1, withThousandsSeparator: true) . ' %' : '—'" :tone="($report['totals']['coverage_pct'] ?? 100) < 100 ? 'warning' : 'success'" />
        </div>

        <x-charts.bar :title="$group === 'week' ? __('Plan vs. Ist je Woche') : __('Plan vs. Ist je Tag')"
                      :unit="__('Stunden')"
                      :series="$chartSeries"
                      :x-label="$group === 'week' ? __('Woche') : __('Datum')"
                      :y-label="__('Plan (h)')"
                      :y2-label="__('Ist (h)')" />

        <x-card :title="__('Je Schichttyp')" icon="schedule">
            <x-table table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string" default="asc">{{ __('Schichttyp') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Schichten') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Plan (h)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ist (h)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Differenz (h)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Abdeckung') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>
                            @if ($row['color'] !== null)
                                <span class="badge badge-sm border-0 mr-1" style="background-color:{{ $row['color'] }};color:#fff;">{{ $row['name'] }}</span>
                            @else
                                {{ $row['name'] }}
                            @endif
                            @if ($row['without_window'] > 0)
                                <span class="tooltip" data-tip="{{ __('Schichten ohne Zeitfenster: Soll nicht bestimmbar, Ist = Tages-Anwesenheit.') }}">
                                    <x-status-badge size="xs" tone="neutral">{{ $row['without_window'] }} {{ __('ohne Zeitfenster') }}</x-status-badge>
                                </span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['shifts'] }}">{{ $row['shifts'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['plan_minutes'] }}">{{ $fmtH($row['plan_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['actual_minutes'] }}">{{ $fmtH($row['actual_minutes']) }}</td>
                        <td class="text-right tabular-nums {{ $row['delta_minutes'] < 0 ? 'text-error' : '' }}" data-sort-value="{{ $row['delta_minutes'] }}">{{ $fmtH($row['delta_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['coverage_pct'] ?? -1 }}">
                            @if ($row['coverage_pct'] === null)
                                <span class="text-base-content/40">—</span>
                            @elseif ($row['coverage_pct'] < 100)
                                {{-- Unterdeckung hervorheben (§2.3): Ist deckt das Soll-Fenster nicht. --}}
                                <x-status-badge size="xs" tone="warning">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['coverage_pct'], 1, withThousandsSeparator: true) }} %</x-status-badge>
                            @else
                                {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['coverage_pct'], 1, withThousandsSeparator: true) }} %
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <p class="mt-2 text-xs text-base-content/60">
                {{ __('Soll = veröffentlichte/bestätigte Schichten × Fensterdauer; Ist = Überlappung der Anwesenheiten mit dem Schichtfenster.') }}
            </p>
        </x-card>
    @endif
</x-page-shell>
@endsection

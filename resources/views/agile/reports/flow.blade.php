{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : flow.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Fluss-Bericht (Feature 064, P9/MVP-147): CFD, WIP-Historie, Durchsatz,
     Control Chart (Cycle-Time mit Perzentilen), Aging-WIP, Blockier-Pareto,
     Backlog-Zu-/Abgang, Flow-Effizienz (nur bei vollständiger
     Spalten-Klassifikation — sonst Datenqualitäts-Hinweis). --}}

@extends('layouts.app')

@section('title', __('Fluss-Bericht') . ' — ' . $project->name)
@section('nav-title', __('Fluss-Bericht'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $project->name }}</x-slot:title>
            <x-slot:subtitle>{{ __('Kennzahlen aus Ereignissen und Snapshots (Definition v:version).', ['version' => $cfd->metricVersion]) }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('agile.reports.export.csv', [$project, 'throughput'])" show-label>{{ __('CSV Durchsatz') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('agile.reports.export.csv', [$project, 'cfd'])" show-label>{{ __('CSV CFD') }}</x-icon-btn>
                <x-icon-btn icon="monitoring" tone="ghost" size="sm" :href="route('agile.reports.sprint', $project)" show-label>{{ __('Sprint-Cockpit') }}</x-icon-btn>
                <x-icon-btn icon="view_kanban" tone="ghost" size="sm" :href="route('agile.board', $project)" show-label>{{ __('Zum Board') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('agile.reports.flow', $project)" :reset="route('agile.reports.flow', $project)">
        <x-date-range from-name="from" to-name="to" :from="$from->toDateString()" :to="$to->toDateString()" size="sm" />
        <x-icon-btn icon="filter_alt" tone="ghost" size="sm" type="submit" show-label>{{ __('Anzeigen') }}</x-icon-btn>
    </x-filter-bar>

    {{-- Flow-Effizienz: Kennzahl ODER Datenqualitäts-Hinweis. --}}
    @if ($flowEfficiency->data['available'])
        <x-card :title="__('Flow-Effizienz')">
            <p class="text-2xl font-semibold tabular-nums">{{ $flowEfficiency->data['median'] }} %</p>
            <p class="text-xs text-base-content/60">{{ __('Median über :count erledigte Elemente (Arbeitszeit an der Gesamtdurchlaufzeit).', ['count' => $flowEfficiency->data['sample_size']]) }}</p>
        </x-card>
    @else
        <div class="alert alert-warning">
            <x-icon name="data_alert" />
            <span>{{ __('Flow-Effizienz wird nicht berechnet: Spalten ohne Berichtsrolle (:columns). Bitte in den Board-Einstellungen klassifizieren.', ['columns' => implode(', ', $flowEfficiency->data['unclassified_columns'])]) }}</span>
        </div>
    @endif

    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.area-stack :title="__('Kumulatives Flussdiagramm (CFD)')"
                             :unit="__('Elemente')"
                             :computed-at="$cfd->computedAt"
                             :bands="[
                                 ['key' => 'done', 'label' => __('Erledigt')],
                                 ['key' => 'in_progress', 'label' => __('In Arbeit')],
                                 ['key' => 'open', 'label' => __('Offen')],
                             ]"
                             :series="collect($cfd->data['series'])->map(fn($row) => ['x' => $row['date'], 'done' => $row['done'], 'in_progress' => $row['in_progress'], 'open' => $row['open']])->all()" />

        <x-charts.line :title="__('WIP-Historie')"
                       :unit="__('Elemente in Arbeit')"
                       :computed-at="$wip->computedAt"
                       :x-label="__('Datum')"
                       :series="collect($wip->data['series'])->map(fn($row) => ['x' => $row['date'], 'y' => $row['wip']])->all()" />

        <x-charts.bar :title="__('Durchsatz je Woche')"
                      :unit="__('erledigte Elemente')"
                      :computed-at="$throughput->computedAt"
                      :x-label="__('Woche')"
                      :series="$throughputSeries" />

        <x-charts.scatter :title="__('Control Chart — Cycle-Time')"
                          :unit="__('Stunden')"
                          :computed-at="$leadCycle->computedAt"
                          :x-label="__('Element')"
                          :percentiles="array_filter([
                              'P50' => $leadCycle->data['cycle']['p50'],
                              'P85' => $leadCycle->data['cycle']['p85'],
                              'P95' => $leadCycle->data['cycle']['p95'],
                          ], fn($v) => $v > 0)"
                          :series="$cycleItems" />

        <x-charts.pareto :title="__('Blockierdauer je Grund (Pareto)')"
                         :unit="__('Stunden')"
                         :computed-at="$blocked->computedAt"
                         :series="$blockedSeries" />

        <x-charts.bar :title="__('Backlog-Zu- und -Abgang je Woche')"
                      :unit="__('Elemente')"
                      :computed-at="$backlogFlow->computedAt"
                      :x-label="__('Woche')"
                      :y-label="__('Neu')"
                      :y2-label="__('Erledigt')"
                      :series="collect($backlogFlow->data['weeks'])->map(fn($row, $week) => ['x' => $week, 'y' => $row['added'], 'y2' => $row['done']])->values()->all()" />
    </div>

    <x-card :title="__('Aging-WIP (aktuell in Arbeit)')">
        @if (count($aging->data['items']) === 0)
            <x-empty-state icon="timelapse" :title="__('Kein Element in Arbeit.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Element') }}</th>
                        <th>{{ __('Spalte') }}</th>
                        <th class="text-right">{{ __('Alter (Tage)') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($aging->data['items'] as $row)
                    <tr>
                        <td>{{ $row['title'] ?? '—' }}</td>
                        <td class="text-sm text-base-content/60">{{ $row['column'] ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row['age_days'] ?? '—' }}</td>
                        <td>
                            @if ($row['blocked'])
                                <x-status-badge tone="error" size="xs">{{ __('blockiert') }}</x-status-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

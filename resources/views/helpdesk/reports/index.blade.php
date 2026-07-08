{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Helpdesk-Bericht (Feature 065, MVP-159): Kennzahlen mit den
     x-charts.*-Komponenten aus 064; Queue-Ebene ist die kleinste
     Aggregation — bewusst keine Agenten-Ranglisten. --}}

@extends('layouts.app')
@section('title', __('Helpdesk-Bericht'))
@section('nav-title', __('Helpdesk-Bericht'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Helpdesk-Bericht') }}</x-slot:title>
        <x-slot:subtitle>{{ __('Kennzahlen aus Zeitstempeln und SLA-Uhr-Segmenten (Definition v:version).', ['version' => $metricVersion]) }}</x-slot:subtitle>
    </x-page-toolbar>

    <x-filter-bar :action="route('helpdesk.reports.index')" :reset="route('helpdesk.reports.index')">
        <x-date-range from-name="from" to-name="to" :from="$from->toDateString()" :to="$to->toDateString()" size="sm" />
        <x-icon-btn icon="filter_alt" tone="ghost" size="sm" type="submit" show-label>{{ __('Anzeigen') }}</x-icon-btn>
    </x-filter-bar>

    <div class="grid gap-3 md:grid-cols-3">
        <x-card :title="__('SLA-Erfüllung (Reaktion)')">
            <p class="text-2xl font-semibold tabular-nums">{{ $compliance['reaction_met'] }} %</p>
        </x-card>
        <x-card :title="__('SLA-Erfüllung (Lösung)')">
            <p class="text-2xl font-semibold tabular-nums">{{ $compliance['resolution_met'] }} %</p>
        </x-card>
        <x-card :title="__('Tickets im Zeitraum')">
            <p class="text-2xl font-semibold tabular-nums">{{ $compliance['total'] }}</p>
        </x-card>
    </div>

    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('Ticketvolumen je Woche')"
                      :unit="__('Tickets')"
                      :x-label="__('Woche')"
                      :series="collect($volume)->map(fn($queues, $week) => ['x' => $week, 'y' => array_sum($queues)])->values()->all()" />

        <x-charts.bar :title="__('Wartezeiten nach Verursacher')"
                      :unit="__('Stunden')"
                      :x-label="__('Grund')"
                      :series="collect($waiting)->map(fn($hours, $reason) => ['x' => $reason, 'y' => $hours])->values()->all()" />
    </div>

    <x-card :title="__('Reaktions-/Lösungszeiten (Stunden, Pausen abgezogen)')">
        <x-table bare>
            <x-slot:head>
                <tr><th></th><th class="text-right">P50</th><th class="text-right">P85</th><th class="text-right">P95</th><th class="text-right">n</th></tr>
            </x-slot:head>
            <tr><td>{{ __('Reaktion') }}</td><td class="text-right tabular-nums">{{ $times['reaction']['p50'] }}</td><td class="text-right tabular-nums">{{ $times['reaction']['p85'] }}</td><td class="text-right tabular-nums">{{ $times['reaction']['p95'] }}</td><td class="text-right tabular-nums">{{ $times['reaction']['count'] }}</td></tr>
            <tr><td>{{ __('Lösung') }}</td><td class="text-right tabular-nums">{{ $times['resolution']['p50'] }}</td><td class="text-right tabular-nums">{{ $times['resolution']['p85'] }}</td><td class="text-right tabular-nums">{{ $times['resolution']['p95'] }}</td><td class="text-right tabular-nums">{{ $times['resolution']['count'] }}</td></tr>
        </x-table>
    </x-card>

    <div class="grid gap-3 xl:grid-cols-2">
        <x-card :title="__('Change-Ausgänge')">
            @if ($changeOutcomes === [])
                <x-empty-state icon="published_with_changes" :title="__('Keine abgeschlossenen Changes im Zeitraum.')" compact />
            @else
                <x-table bare>
                    <x-slot:head><tr><th>{{ __('Outcome') }}</th><th class="text-right">{{ __('Anzahl') }}</th></tr></x-slot:head>
                    @foreach ($changeOutcomes as $outcome => $count)
                        <tr><td>{{ $outcome }}</td><td class="text-right tabular-nums">{{ $count }}</td></tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card :title="__('Problem-Bestand nach Status')">
            @if ($problemBacklog === [])
                <x-empty-state icon="troubleshoot" :title="__('Keine Probleme erfasst.')" compact />
            @else
                <x-table bare>
                    <x-slot:head><tr><th>{{ __('Status') }}</th><th class="text-right">{{ __('Anzahl') }}</th></tr></x-slot:head>
                    @foreach ($problemBacklog as $status => $count)
                        <tr><td>{{ $status }}</td><td class="text-right tabular-nums">{{ $count }}</td></tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>

    <x-card :title="__('Katalog-Nachfrage')">
        @if ($catalogDemand === [])
            <x-empty-state icon="storefront" :title="__('Keine Service-Requests im Zeitraum.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Katalogeintrag') }}</th>
                        <th class="text-right">{{ __('Anzahl') }}</th>
                        <th class="text-right">{{ __('Genehmigung (Median h)') }}</th>
                        <th class="text-right">{{ __('Erfüllung (Median h)') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($catalogDemand as $row)
                    <tr>
                        <td>{{ $row['item'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['approval_median_hours'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['fulfillment_median_hours'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection

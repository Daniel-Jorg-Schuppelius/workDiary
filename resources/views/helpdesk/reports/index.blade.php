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
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ __('Kennzahlen aus Zeitstempeln und SLA-Uhr-Segmenten (Definition v:version).', ['version' => $metricVersion]) }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('helpdesk.reports.csv', ['metric' => 'volume', 'from' => $from->toDateString(), 'to' => $to->toDateString()])" show-label>{{ __('CSV Volumen') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('helpdesk.reports.csv', ['metric' => 'fcr', 'from' => $from->toDateString(), 'to' => $to->toDateString()])" show-label>{{ __('CSV FCR') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('helpdesk.reports.csv', ['metric' => 'aging', 'from' => $from->toDateString(), 'to' => $to->toDateString()])" show-label>{{ __('CSV Aging') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('helpdesk.reports.csv', ['metric' => 'satisfaction', 'from' => $from->toDateString(), 'to' => $to->toDateString()])" show-label>{{ __('CSV Zufriedenheit') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('helpdesk.reports.csv', ['metric' => 'knowledge', 'from' => $from->toDateString(), 'to' => $to->toDateString()])" show-label>{{ __('CSV Probleme trotz Artikel') }}</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm" :href="route('helpdesk.reports.pdf', ['from' => $from->toDateString(), 'to' => $to->toDateString()])" show-label>{{ __('PDF-Bericht') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

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

    {{-- KPI-Karten MVP-159: FCR/Wiederöffnung/Weiterleitung getrennt;
         Basis = gelöste Tickets im Zeitraum. Drilldown-Links signiert. --}}
    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-card :title="__('Erstlösungsquote (FCR)')">
            <p class="text-2xl font-semibold tabular-nums">{{ $fcr['fcr_rate'] }} %</p>
            <p class="text-xs text-base-content/60">{{ __(':fcr von :total gelösten Tickets ohne Wiedereröffnung/Weiterleitung.', ['fcr' => $fcr['fcr'], 'total' => $fcr['total']]) }}</p>
        </x-card>
        <x-card :title="__('Wiederöffnungsquote')">
            <p class="text-2xl font-semibold tabular-nums">{{ $fcr['reopened_rate'] }} %</p>
            <p class="text-xs"><a href="{{ $reopenedUrl }}" class="link">{{ __(':count Tickets anzeigen', ['count' => $fcr['reopened']]) }}</a></p>
        </x-card>
        <x-card :title="__('Weiterleitungsquote')">
            <p class="text-2xl font-semibold tabular-nums">{{ $fcr['requeued_rate'] }} %</p>
            <p class="text-xs"><a href="{{ $requeuedUrl }}" class="link">{{ __(':count Tickets anzeigen', ['count' => $fcr['requeued']]) }}</a></p>
        </x-card>
        <x-card :title="__('Zufriedenheit (Ø)')">
            <p class="text-2xl font-semibold tabular-nums">{{ $satisfaction['average'] }}</p>
            <p class="text-xs text-base-content/60">{{ __(':count Bewertungen (1–5).', ['count' => $satisfaction['responses']]) }}</p>
        </x-card>
        <x-card :title="__('Rücklaufquote')">
            <p class="text-2xl font-semibold tabular-nums">{{ $satisfaction['response_rate'] }} %</p>
            <p class="text-xs text-base-content/60">{{ __(':responses Antworten auf :closed gelöste Tickets.', ['responses' => $satisfaction['responses'], 'closed' => $satisfaction['closed_total']]) }}</p>
        </x-card>
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('Ticketvolumen je Woche')"
                      :unit="__('Tickets')"
                      :x-label="__('Woche')"
                      :series="$volumeSeries" />

        <x-charts.bar :title="__('Wartezeiten nach Verursacher')"
                      :unit="__('Stunden')"
                      :x-label="__('Grund')"
                      :series="$waitingSeries" />

        <x-charts.bar :title="__('Aging offener Tickets (Altersbänder)')"
                      :unit="__('offene Tickets')"
                      :x-label="__('Alter in Tagen')"
                      :series="$agingSeries" />

        <x-charts.bar :title="__('Zufriedenheits-Verteilung (1–5)')"
                      :unit="__('Bewertungen')"
                      :x-label="__('Score')"
                      :series="$satisfactionSeries" />
    </div>

    <x-card :title="__('FCR je Queue')">
        @if ($fcr['queues'] === [])
            <x-empty-state icon="support_agent" :title="__('Keine gelösten Tickets im Zeitraum.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Queue') }}</th>
                        <th class="text-right">{{ __('Gelöst') }}</th>
                        <th class="text-right">{{ __('FCR') }}</th>
                        <th class="text-right">{{ __('FCR-Quote') }}</th>
                        <th class="text-right">{{ __('Wiedereröffnet') }}</th>
                        <th class="text-right">{{ __('Weitergeleitet') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($fcr['queues'] as $queue => $row)
                    <tr>
                        <td>{{ $queue }}</td>
                        <td class="text-right tabular-nums">{{ $row['total'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['fcr'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['fcr_rate'] }} %</td>
                        <td class="text-right tabular-nums">{{ $row['reopened'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['requeued'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card :title="__('Reaktions-/Lösungszeiten (Stunden, Pausen abgezogen)')">
        <x-table bare>
            <x-slot:head>
                <tr><th></th><th class="text-right">P50</th><th class="text-right">P85</th><th class="text-right">P95</th><th class="text-right">n</th></tr>
            </x-slot:head>
            <tr><td>{{ __('Reaktion') }}</td><td class="text-right tabular-nums">{{ $times['reaction']['p50'] }}</td><td class="text-right tabular-nums">{{ $times['reaction']['p85'] }}</td><td class="text-right tabular-nums">{{ $times['reaction']['p95'] }}</td><td class="text-right tabular-nums">{{ $times['reaction']['count'] }}</td></tr>
            <tr><td>{{ __('Lösung') }}</td><td class="text-right tabular-nums">{{ $times['resolution']['p50'] }}</td><td class="text-right tabular-nums">{{ $times['resolution']['p85'] }}</td><td class="text-right tabular-nums">{{ $times['resolution']['p95'] }}</td><td class="text-right tabular-nums">{{ $times['resolution']['count'] }}</td></tr>
        </x-table>
    </x-card>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
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

    {{-- Probleme trotz Wissensartikel (Feature 011, MVP-338/Bauturbo A20):
         neue Incidents NACH Artikel-Publikation, meiste Vorkommen zuerst —
         die Handlungsliste „Artikel unwirksam oder unauffindbar?".
         Drilldown je Artikel signiert (Muster P11). --}}
    <x-card :title="__('Probleme trotz Wissensartikel')">
        @if ($recurring === [])
            <x-empty-state icon="menu_book" :title="__('Keine neuen Incidents nach Artikel-Publikation im Zeitraum.')" compact />
        @else
            <p class="mb-2 text-xs text-base-content/60">{{ __('Wiederkehrende Störungen trotz veröffentlichtem Known-Error-Artikel — meiste Vorkommen zuerst (Artikel unwirksam oder unauffindbar?).') }}</p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Wissensartikel') }}</th>
                        <th>{{ __('Problem') }}</th>
                        <th>{{ __('Publiziert') }}</th>
                        <th class="text-right">{{ __('Neue Incidents') }}</th>
                        <th>{{ __('Trend') }}</th>
                        <th>{{ __('Letztes Vorkommen') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($recurring as $row)
                    <tr>
                        <td><a href="{{ route('knowledge.show', $row['article']) }}" class="link">{{ \Illuminate\Support\Str::limit($row['article']->title, 60, '…') }}</a></td>
                        <td class="text-sm text-base-content/60">{{ \Illuminate\Support\Str::limit(implode(', ', $row['problems']), 60, '…') }}</td>
                        <td class="text-sm text-base-content/60">{{ $row['article']->published_at?->isoFormat('L') ?? '—' }}</td>
                        <td class="text-right tabular-nums"><a href="{{ $row['url'] }}" class="link">{{ $row['count'] }}</a></td>
                        <td>
                            @if ($row['trend'] === 'rising')
                                <x-status-badge tone="error" size="xs" icon="trending_up">{{ __('Steigend') }}</x-status-badge>
                            @elseif ($row['trend'] === 'falling')
                                <x-status-badge tone="success" size="xs" icon="trending_down">{{ __('Fallend') }}</x-status-badge>
                            @else
                                <x-status-badge tone="neutral" size="xs" icon="trending_flat">{{ __('Gleichbleibend') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-sm text-base-content/60">{{ $row['last_at']?->isoFormat('L LT') ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

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

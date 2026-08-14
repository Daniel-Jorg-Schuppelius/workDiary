{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Kundenanalyse'))
@section('nav-title', __('Kundenanalyse'))

@section('content')
@php
    $fmt = fn (int $min): string => \App\Support\Formats::duration($min, 'clock');
    $linkParams = array_filter(array_merge(
        ['min_minutes' => $minMinutes > 0 ? $minMinutes : null, 'hide_zero' => $hideZero ? 1 : null],
        $standardFilters->toQueryParams(),
    ));
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Aufwand, Nacharbeit und Nicht-Abrechenbares je Kunde.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.customers', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_view" tone="outline" size="sm"
                            :href="route('reports.customers', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>Excel</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.customers', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.customer-analysis" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.customers')" :reset="route('reports.customers')">
        @include('reports._standard_filters', ['idPrefix' => 'customers'])
        <x-filter-field :label="__('Mindest-Aufwand (Minuten)')" for="rep-min-minutes" inline>
            <input id="rep-min-minutes" type="number" name="min_minutes" value="{{ $minMinutes }}" min="0" class="input input-sm input-bordered w-24" />
        </x-filter-field>
        <x-filter-toggle name="hide_zero" id="customers-hide-zero"
                         :label="__('Kunden ohne Werte ausblenden')"
                         :title="__('Nur Kunden mit Aktivität im Zeitraum anzeigen (ohne reine Nullzeilen).')"
                         :checked="$hideZero" data-autosubmit />
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.pareto :title="__('Stunden je Kunde (Top 20)')" unit="h" :series="$customerHoursSeries" :x-label="__('Kunde')" :y-label="__('Stunden')" />
        <x-charts.line :title="__('Auftragseingang :per', ['per' => $periodPhrase])" :unit="__('Aufträge')" :series="$trendSeries" :x-label="$periodAxis" :y-label="__('Aufträge')" />
    </div>
    <x-charts.bar-h :title="__('Offene Punkte je Kunde (Top 15)')" :unit="__('Offene Punkte')" :series="$openIssuesSeries" :x-label="__('Kunde')" :y-label="__('Offene Punkte')" />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-table bare table-sort="client">
            <x-slot:head>
                <tr><x-table.th sort type="string">{{ __('Top 5 Aufwand') }}</x-table.th><x-table.th sort type="number" align="right">{{ __('Min.') }}</x-table.th></tr>
            </x-slot:head>
            @forelse($topByMinutes as $row)
                <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums">{{ $row['totalMinutes'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </x-table>

        <x-table bare table-sort="client">
            <x-slot:head>
                <tr><x-table.th sort type="string">{{ __('Top 5 Nacharbeit') }}</x-table.th><x-table.th sort type="number" align="right">{{ __('Einträge') }}</x-table.th></tr>
            </x-slot:head>
            @forelse($topByRework as $row)
                <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums">{{ $row['reworkEntryCount'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </x-table>

        <x-table bare table-sort="client">
            <x-slot:head>
                <tr><x-table.th sort type="string">{{ __('Top 5 nicht abrechenbar') }}</x-table.th><x-table.th sort type="number" align="right">{{ __('Min.') }}</x-table.th></tr>
            </x-slot:head>
            @forelse($topByNonBillable as $row)
                <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums">{{ $row['nonBillableMinutes'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <x-card class="mt-4">
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if($rows->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">analytics</span>' :title="__('Keine Kundendaten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Aufträge') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Gesamt') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Abrechenbar') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Nicht abrechenbar') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anteil %') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Nacharbeit') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Offene Punkte') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Eskaliert') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ø Min./Auftrag') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Trend 30d') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach($rows as $row)
                    @php
                        $drilldownBase = [
                            'customer' => \App\Support\Sqid::encode(\App\Models\Customer::class, $row['customerId']),
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                            'project' => \App\Support\Sqid::encode(\App\Models\Project::class, $projectId),
                            'user' => \App\Support\Sqid::encode(\App\Models\User::class, $userId),
                        ];
                        $reportDrilldownBase = array_filter([
                            'customer_id' => \App\Support\Sqid::encode(\App\Models\Customer::class, $row['customerId']),
                            'project_id' => \App\Support\Sqid::encode(\App\Models\Project::class, $projectId),
                            'user_id' => \App\Support\Sqid::encode(\App\Models\User::class, $userId),
                        ]);
                    @endphp
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('diary.index', $drilldownBase) }}" class="link link-hover">
                                {{ $row['customerName'] }}
                            </a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('diary.index', $drilldownBase) }}" class="link link-hover">{{ $row['entryCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums" title="{{ $fmt($row['totalMinutes']) }}">
                            <a href="{{ route('diary.index', $drilldownBase) }}" class="link link-hover">{{ $row['totalMinutes'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $row['billableMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['nonBillableMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['nonBillableShare'], 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.customers.drilldown.protocols', $reportDrilldownBase) }}" class="link link-hover">{{ $row['reworkEntryCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.customers.drilldown.open-issues', $reportDrilldownBase) }}" class="link link-hover">{{ $row['openIssueCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.customers.drilldown.open-issues', array_merge($reportDrilldownBase, ['escalated' => 1])) }}" class="link link-hover">{{ $row['escalationCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $row['avgEntryMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['trend30d'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
